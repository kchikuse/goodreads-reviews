<?php

require_once 'libs/cache.class.php';
require_once 'libs/simple_html_dom.php';


class Scraper
{
    function __construct( $Scraper )
    {
        $this->cache = new Cache();
        $this->cache->eraseExpired();
        $this->scraper = new $Scraper();
    }

    function reviews( $isbn )
    {
        $key = get_class($this->scraper) . $isbn;

        if ( $this->cache->isCached($key) ) 
        {
            $result = $this->cache->retrieve( $key );
        }
        else
        {
            $result = $this->scraper->reviews( $isbn );

            if ( ! empty($result->reviews) )
            {
                usort($result->reviews, function($a, $b) {
                    return $b->likes - $a->likes;
                });

                $this->cache->store( $key, $result, 3600 );
            }
        }
                
        return $result;
    }
}



class Goodreads
{
    function reviews( $isbn )
    {
        //$url = 'http://localhost/fakesite/meta.json';

        $url = 'http://www.goodreads.com/book/auto_complete?format=json&q=' . $isbn;

        $output = [];

        $html = $this->get_html( $url );

        if ( ! empty($html) ) 
        {
            $output = $this->get_meta( $html );

            $output->reviews = $this->get_rows( $html );
        }

        return $output;
    }

    private function get_html( $url )
    {
        $meta = json_decode( file_get_contents($url), true );

        if ( empty($meta) )
        {
            return [];
        }

        return file_get_html( $meta[0]['description']['fullContentUrl'] );
    }

    private function get_rows( $html )
    {
        $reviews = [];

        $rows = $html->find('#other_reviews .review');

        foreach ( $rows as $row ) 
        {
            $content = trim( $row->find('.reviewText', 0)->plaintext );

            if ( strlen($content) > 20 ) 
            {
                $reviews[] = (object) 
                [
                    'content' => str_replace( '...more', '', $content ),
                    'rating'  => count($row->find('.staticStars .p10')),
                    'likes'   => (int) trim( $row->find('.likesCount', 0)->plaintext )
                ];
            }
        }

        return $reviews;
    }

    private function get_meta( $html )
    {
        return (object) 
        [
            'title'  => trim( $html->find('#bookTitle', 0)->plaintext ),
            'cover'  => trim( $html->find('#coverImage', 0)->getAttribute('src') ),
            'rating' => trim( $html->find('[itemprop=ratingValue]', 0)->plaintext ),
            'totalr' => trim( $html->find('#bookMeta .actionLinkLite .count', 0)->plaintext )
        ];
    }
}



class Amazon
{
    function reviews( $isbn )
    {
        //$url = 'http://localhost/fakesite/9781447273288_Books.htm';

        $url = 'https://www.amazon.com/s/ref=nb_sb_noss?field-keywords=' . $isbn;

        $output = [];

        $html = $this->get_html( $url );

        if ( ! empty($html) ) 
        {
            $output = $this->get_meta( $html );

            $output->reviews = $this->get_rows( $html );
        }

        return $output;
    }

    private function get_html( $url )
    {
        $html = file_get_html( $url );

        $error = $html->find( '#noResultsTitle', 0 );

        if ( ! is_null($error) )
        {
            return [];
        }

        return file_get_html( $html->find('.s-access-detail-page', 0)->getAttribute('href') );
    }

    private function get_rows( $html )
    {
        $reviews = [];

        $rows = $html->find('#revMH #revMHRL .celwidget');

        foreach ( $rows as $row )
        {
            $likes = explode( ' ', trim($row->find('.cr-vote-buttons', 0)->plaintext) )[0];

            $reviews[] = (object)
            [
                'content' => trim( $row->find('.a-section', 0)->plaintext ),
                'rating'  => (float) trim( $row->find('.a-icon-star', 0)->plaintext ),
                'likes'   => strtolower( $likes ) === 'one' ? 1 : ( (int) $likes ?: 0 )
            ];
        }

        return $reviews;
    }

    private function get_meta( $html )
    {
        $title = $html->find('#productTitle', 0);

        if ( is_null($title) )
        {
            $title = $html->find('#ebooksProductTitle', 0);
        }

        return (object) 
        [
            'title'  => trim( $title->plaintext ),
            'cover'  => $html->find('#imgBlkFront', 0)->getAttribute('src'),
            'totalr' => (int) $html->find('#acrCustomerReviewText', 0)->plaintext,
            'rating' => (float) $html->find('.reviewCountTextLinkedHistogram', 0)->getAttribute('title')
        ];
    }
}



function get_info( $isbn, $Site ) 
{
    $site = strtolower( $Site );

    $sites = [ 'amazon', 'goodreads' ];

    if( ! in_array($site, $sites) )
    {
        $site = $sites[ 1 ];
    }

    $tuph = [];

    if ( ! empty($isbn) )
    {
        $scraper = new Scraper( $site );

        $tuph = $scraper->reviews( $isbn );
    }

    $empty  = empty($tuph->reviews) ? 'empty' : '';
    $title  = empty($tuph->reviews) && $isbn ? 'book not found' : $tuph->title;
    $toggle = sprintf( '?isbn=%s&site=%s', $isbn, $site === $sites[ 0 ] ? $sites[ 1 ] : $sites[ 0 ] );
    $stars  = sprintf( '%s %s / %s reviews', str_repeat('<star>☆</star>', $tuph->rating), $tuph->rating, $tuph->totalr );

    return (object)
    [
        'isbn'      => $isbn,
        'site'      => $site,
        'empty'     => $empty,
        'title'     => $title, 
        'stars'     => $stars,
        'toggle'    => $toggle,
        'cover'     => $tuph->cover,
        'reviews'   => $tuph->reviews,
        'icon'      => 'icons/'. $site .'.ico'
    ];
}