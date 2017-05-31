<?php

namespace Oconal\Walldrop;

use Exception;
use Wallhaven\Category;
use Wallhaven\Order;
use Wallhaven\Purity;
use Wallhaven\Sorting;
use Wallhaven\Wallpaper;
use Wallhaven\WallpaperList;

class WalldropService
{
    private const PAGE_SIZE = 64;
    /** @var Wallhaven */
    private $wallhaven;
    /** @var string */
    private $uncheckedPath;
    /** @var string */
    private $checkedPath;
    /** @var string */
    private $unwantedPath;

    /**
     * @param Wallhaven $wallhaven Configured wallhaven client.
     * @param string $uncheckedPath Path to folder where unchecked images are stored.
     * @param string $checkedPath Path to folder where checked images are stored.
     * @param string $unwantedPath Path to folder where unwanted images are stored.
     */
    public function __construct(
        Wallhaven $wallhaven,
        string $uncheckedPath,
        string $checkedPath,
        string $unwantedPath
    ) {
        $this->wallhaven = $wallhaven;
        $this->uncheckedPath = $uncheckedPath;
        $this->checkedPath = $checkedPath;
        $this->unwantedPath = $unwantedPath;
    }

    /** * Synchronise your dropbox folder with your wallhaven favourites list. */
    public function sync(): void
    {
        $subscriptions = $this->wallhaven->getSubscriptions();
        foreach ($subscriptions['tags'] as $tag) {
            $page = 1;
            do {
                $wallpapers = $this->wallhaven->search(
                    "\"#{$tag}\"",
                    Category::ALL,
                    Purity::SFW,
                    Sorting::DATE_ADDED,
                    Order::DESC,
                    [],
                    [],
                    $page
                );
                $this->download($wallpapers, ['tag', $tag]);
            } while ($wallpapers->count() >= self::PAGE_SIZE); // Deteremine if there are more pages to come
        }
    }

    /**
     * @param WallpaperList $wallpapers The wallpapers to be downloaded.
     * @param array $sourceHierarchy The source hierarchy (e.g., ['tag', 'Youjo Senki'])
     * @throws Exception When the resolution isn't recognised.
     */
    private function download(WallpaperList $wallpapers, array $sourceHierarchy): void
    {
        /** @var Wallpaper $wallpaper */
        foreach ($wallpapers as $wallpaper) {
            if ($wallpaper->getPurity() !== Purity::SFW) {
                continue;
            }
            switch ($wallpaper->getResolution()) {
                default:
                    throw new Exception('Unrecognised resolution: ' . $wallpaper->getResolution());
            }
            if ($this->exists($wallpaper, $sourceHierarchy)) {
                continue;
            }
            $path = $this->getUncheckedPath($wallpaper, $sourceHierarchy);
            echo "Downloading to {$path}\n";
            $this->wallhaven->download($wallpaper, $path);
        }
    }

    /**
     * @param Wallpaper $wallpaper The wallpaper to find the path for.
     * @param array $sourceHierarchy The source hierarchy (e.g., ['tag', 'Youjo Senki']).
     * @return string The fully qualified unchecked path for the destination file.
     */
    private function getUncheckedPath(Wallpaper $wallpaper, array $sourceHierarchy): string
    {
        return $this->getPath($wallpaper, $this->uncheckedPath, $sourceHierarchy);
    }

    /**
     * @param Wallpaper $wallpaper The wallpaper to find the path for.
     * @param array $sourceHierarchy The source hierarchy (e.g., ['tag', 'Youjo Senki']).
     * @return string The fully qualified unwanted path for the destination file.
     */
    private function getUnwantedPath(Wallpaper $wallpaper, array $sourceHierarchy): string
    {
        return $this->getPath($wallpaper, $this->unwantedPath, $sourceHierarchy);
    }

    /**
     * @param Wallpaper $wallpaper The wallpaper to find the path for.
     * @param string $basePath The base path.
     * @param array $sourceHierarchy The source hierarchy (e.g., ['tag', 'Youjo Senki'])
     * @return string The fully qualified path for the destination file.
     */
    private function getPath(Wallpaper $wallpaper, string $basePath, array $sourceHierarchy): string
    {
        $fileComponents = [];

        foreach ($sourceHierarchy as $sourceHierarchyEntry) {
            $fileComponents[] = $this->sanitise($sourceHierarchyEntry);
        }

        $fileComponents[] = basename($wallpaper->getImageUrl());

        return $basePath . DIRECTORY_SEPARATOR . implode('-', $fileComponents);
    }

    /**
     * @param string $string The string to be sanitised.
     * @return string A directory name that's safe to use with PHP's file and directory functions.
     */
    private function sanitise(string $string): string
    {
        return $string;
    }

    /**
     * @param Wallpaper $wallpaper The wallpaper to search for.
     * @param array $sourceHierarchy The source hierarchy (e.g., ['tag', 'Youjo Senki'])).
     * @return bool True if the file exists in any valid directory. False otherwise.
     */
    private function exists(Wallpaper $wallpaper, array $sourceHierarchy): bool
    {
        $paths = [
            $this->getUncheckedPath($wallpaper, $sourceHierarchy),
            $this->getCheckedPath($wallpaper, $sourceHierarchy),
            $this->getUnwantedPath($wallpaper, $sourceHierarchy),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Wallpaper $wallpaper The wallpaper to find the path for.
     * @param array $sourceHierarchy The source hierarchy (e.g., ['tag', 'Youjo Senki']).
     * @return string The fully qualified unchecked path for the destination file.
     */
    private function getCheckedPath(Wallpaper $wallpaper, array $sourceHierarchy): string
    {
        return $this->getPath($wallpaper, $this->checkedPath, $sourceHierarchy);
    }
}
