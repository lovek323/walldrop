<?php

namespace Oconal\Walldrop;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPHtmlParser\Dom;
use Wallhaven\Category;
use Wallhaven\Order;
use Wallhaven\Purity;
use Wallhaven\Sorting;
use Wallhaven\Wallpaper;
use Wallhaven\WallpaperList;

class Wallhaven extends \Wallhaven\Wallhaven
{
    const URL_SUBSCRIPTION = 'https://alpha.wallhaven.cc/subscription';

    public function getSubscriptions(): array
    {
        $client = $this->getClient();

        $result = $client->get(
            self::URL_SUBSCRIPTION
        );

        $body = $result->getBody()->getContents();
        $dom = new Dom();
        $dom->load($body);

        $retval = [
            'userUploads' => [],
            'userCollections' => [],
            'tags' => [],
        ];

        /** @var Dom\HtmlNode[] $tags */
        $tags = $dom->find('div[data-storage-id="tagsubscriptions"] span.tagname');

        foreach ($tags as $tag) {
            $retval['tags'][] = $tag->text();
        }

        return $retval;
    }

    public function getFavourites(): WallpaperList
    {
        return new WallpaperList();
    }

    private function getClient(): Client
    {
        $property = new \ReflectionProperty(\Wallhaven\Wallhaven::class, 'client');
        $property->setAccessible(true);
        /** @var Client $client */
        $client = $property->getValue($this);

        return $client;
    }

    /**
     * Search for wallpapers.
     *
     * @param string $query What to search for. Searching for specific tags can be done with #tagname, e.g.
     *                              <samp>#cars</samp>
     * @param int $categories Categories to include. This is a bit field, e.g.: <samp>Category::GENERAL |
     *                              Category::PEOPLE</samp>
     * @param int $purity Purity of wallpapers. This is a bit field, e.g.: <samp>Purity::SFW |
     *                              Purity::NSFW</samp>
     * @param string $sorting Sorting, e.g. <samp>Sorting::RELEVANCE</samp>
     * @param string $order Order of results. Can be <samp>Order::ASC</samp> or <samp>Order::DESC</samp>
     * @param string[] $resolutions Array of resolutions in the format of WxH, e.g.: <samp>['1920x1080',
     *                              '1280x720']</samp>
     * @param string[] $ratios Array of ratios in the format of WxH, e.g.: <samp>['16x9', '4x3']</samp>
     * @param int $page The id of the page to fetch. This is <em>not</em> a total number of pages to
     *                              fetch.
     *
     * @return WallpaperList Wallpapers.
     */
    public function search(
        $query,
        $categories = Category::ALL,
        $purity = Purity::SFW,
        $sorting = Sorting::RELEVANCE,
        $order = Order::DESC,
        $resolutions = [],
        $ratios = [],
        $page = 1
    )
    {
        $client = $this->getClient();

        $result = $client->get(self::URL_SEARCH, [
            'query' => [
                'q' => $query,
                'categories' => self::getBinary($categories),
                'purity' => self::getBinary($purity),
                'sorting' => $sorting,
                'order' => $order,
                'resolutions' => implode(',', $resolutions),
                'ratios' => implode(',', $ratios),
                'page' => $page,
            ],
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $body = $result->getBody()->getContents();
        $dom = new Dom();
        $dom->load($body);

        /** @var Dom\HtmlNode[] $figures */
        $figures = $dom->find('figure.thumb');

        $wallpapers = new WallpaperList();

        foreach ($figures as $figure) {
            $id = preg_split(
                '#' . self::URL_HOME . self::URL_WALLPAPER . '/#',
                $figure->find('a.preview')->getAttribute('href')
            )[1];

            $classText = $figure->getAttribute('class');
            preg_match("/thumb thumb-(?<id>[0-9]+) thumb-(?<purity>sfw|sketchy|nsfw) thumb-(?<category>general|anime|people)/", $classText, $classMatches);

            $purity = constant('Wallhaven\Purity::' . strtoupper($classMatches['purity']));
            $category = constant('Wallhaven\Category::' . strtoupper($classMatches['category']));
            $resolution = str_replace(' ', '', trim($figure->find('span.wall-res')->text));
            $favorites = (int)$figure->find('.wall-favs')->text;

            $w = new Wallpaper($id, $client);

            $w->setProperties([
                'purity' => $purity,
                'category' => $category,
                'resolution' => $resolution,
                'favorites' => $favorites,
            ]);

            $wallpapers[] = $w;
        }

        return $wallpapers;
    }

    /**
     * Convert a bit field into Wallhaven's format.
     *
     * @param int $bitField Bit field.
     *
     * @return string Converted to binary.
     */
    private static function getBinary($bitField)
    {
        return str_pad(decbin($bitField), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param Wallpaper $wallpaper The wallpaper to be downloaded.
     * @param string $file Where to download the wallpaper.
     */
    public function download(Wallpaper $wallpaper, $file)
    {
        $this->getClient()->get($wallpaper->getImageUrl(), [RequestOptions::SINK => $file]);
    }
}
