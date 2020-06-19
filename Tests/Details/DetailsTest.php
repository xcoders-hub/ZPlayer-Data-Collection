<?php 

use PHPUnit\Framework\TestCase;
use Details\IMDB;

final class DetailsTest extends TestCase
{
    public $IMDB;
 
    protected function setUp():Void
    {
        $this->IMDB = new IMDB();
    }
 
    protected function tearDown():Void
    {
        $this->IMDB = NULL;
    }
 
    public function testParseReleaseDate():Void
    {
        $text_date = 'Release Date: 28 October 2014 (UK)';
        $correct_text_date = '2014-10-28 00:00:00';

        $date = $this->IMDB->parse_date($text_date);

        $this->assertEquals($date, $correct_text_date);
    }

    public function testParseEpisodeDate():Void
    {
        $text_date = '22 Oct. 2019';
        $correct_text_date = '2019-10-22 00:00:00';

        $date = $this->IMDB->parse_date($text_date);

        $this->assertEquals($date, $correct_text_date);
    }

    public function testUrlContentId():Void
    {
        $correct_show_id = 'tt3107288';
       $imdb_url = "https://www.imdb.com/title/$correct_show_id";
       $show_id = $this->IMDB->url_id($imdb_url);

       $this->assertEquals($correct_show_id, $show_id);
    }
}





