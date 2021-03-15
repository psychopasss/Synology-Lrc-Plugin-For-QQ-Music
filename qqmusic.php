<?php

// Must set to false when running on DS Station
const DEBUG = false;
// Set to TURE is Chinese translation is needed
const NEED_TRANSLATION = true;


/**
 * Implements the functions required by Audio Station/DSAudio.
 * <p>
 * Features:
 *   - Sort result according to similarity of artist and title.
 *   - Add Chinese translated lyric if {@code NEED_TRANSLATION} is {@code TRUE}.
 *
 * @author PsychoPass (https://github.com/psychopasss/Synology-Lrc-Plugin-For-QQ-Music)
 * @see https://global.download.synology.com/download/Document/DeveloperGuide/AS_Guide.pdf
 */
class ZainQQLrc {
    private $mArtist = "";
    private $mTitle = "";

    ///////////////////////////// Synology API ///////////////////////////////////////

    /**
     * Searches for a song with the artist and title, and returns the matching result list. Result is sorted based on similarity of artist and title.
     */
    public function getLyricsList($artist, $title, $info) {
        $artist = trim($artist);
        $title = trim($title);
        $this->mArtist = $artist;
        $this->mTitle = $title;
        if ($this->isNullOrEmptyString($title)) {
            return 0;
        }
        $response = $this->search($title);
        if ($this->isNullOrEmptyString($response)) {
            return 0;
        }

        $json = json_decode($response, true);
        $songArray = $json['data']['song']['list'];
        
        // var_dump($songArray);

        if(count($songArray) == 0) {
            return 0;
        }

        // Try to find the titles that match exactly
        $exactMatchArray = array();
        $partialMatchArray = array();
        foreach ($songArray as $song) {
            $lowTitle = strtolower($title);
            // var_dump("源歌名：".$lowTitle);
            $lowResult = strtolower($song['songname']);
            // var_dump("匹配歌名：".$lowResult."<br>");
             if (strtolower($lowTitle) === strtolower($lowResult)) {
                array_push($exactMatchArray, $song);
            } else if (strpos($lowResult, $lowTitle) !== FALSE || strpos($lowTitle, $lowResult) !== FALSE) {
                array_push($partialMatchArray, $song);
            }
        }

        if (count($exactMatchArray) != 0) {
            $songArray = $exactMatchArray;
        } else if (count($partialMatchArray != 0)) {
            $songArray = $partialMatchArray;
        }

        // Get information from songs
        $foundArray = array();
        foreach ($songArray as $song) {
            $elem = array(
                'id' => $song['songid'],
                'artist' => $song['singer'][0]["name"],
                'title' => $song['songname'],
                'alt' => $song['alias'][0] . "; Album: " . $song['albumname']
            );
            // Find the best match artist from all artists belong to a song
            $max = 0;
            foreach ($song['singer'] as $item) {
                $score = $this->getStringSimilarity($artist, $item['name']);
                if ($score > $max) {
                    $max = $score;
                    $elem['artist'] = $item['name'];
                }
            }
            array_push($foundArray, $elem);
        }

        // Sort by best match according to similarity of artist and title
        usort($foundArray, array($this,'cmp'));
        foreach ($foundArray as $song) {
            // add artist, title, id, lrc preview (or additional comment)
            $info->addTrackInfoToList($song['artist'], $song['title'], $song['id'], $song['id'] . "; " . $song['alt']);
        }

        return count($foundArray);
    }

    /**
     * Downloads a lyric with the specific ID. Will have Chinese translation if {@code NEED_TRANSLATION} is {@code TRUE}.
     */
    public function getLyrics($id, $info) {
        $lrc = $this->downloadLyric($id);
        if ($this->isNullOrEmptyString($lrc)) {
            return FALSE;
        }

        $info->addLyrics($lrc, $id);

        return true;
    }

    ///////////////////////////// Utils ///////////////////////////////////////

    /**
     * Gets all lyrics, apart from original one, translated and karaoke versions will also be returned if available.
     */
    private function downloadLyric($music_id) {
        $response = $this->download($music_id);
        if ($this->isNullOrEmptyString($response)) {
            return NULL;
        }

        $json = json_decode($response, true);
        $orgLrc = $json['lyric'];
        $transLrc = $json['trans']; // Chinese translation lyric, but only some songs have

        $resultLrc = $orgLrc;
        if (NEED_TRANSLATION && !$this->isNullOrEmptyString($transLrc)) {
            $resultLrc = "";
            $orgLines = $this->processLrcLine($orgLrc);
            $transLines = $this->processLrcLine($transLrc);

            $transCursor = 0;
            foreach ($orgLines as $elem) {
                $key = $elem['tag']; // time tag
                $value = $elem['lrc']; // lyric line
                $resultLrc .= $key . $value;

                // Find matching translation
                $trans = "";
                if (!$this->isNullOrEmptyString($key)) {
                    $time = $this->getTimeFromTag($key);
                    for ($i = $transCursor; $i < count($transLines); $i++) {
                        $tKey = $transLines[$i]['tag'];                                 
                        if ($this->getTimeFromTag($tKey) > $time) { // trans time tag is greater than org, no match found
                            $transCursor = $i;
                            break;
                        }

                        $tValue = $transLines[$i]['lrc'];
                        // Check for matching time tag
                        if ($key === $tKey) {
                            $transCursor = $i + 1;
                            $trans = $tValue;
                            break;
                        }
                    }
                }
                
                if (!$this->isNullOrEmptyString($trans)) { // $key is empty when it's not time tag, just metadata
                    $resultLrc .= " 【" . $trans . "】";
                }
                $resultLrc .= "\n";
            }
        }
        
        // return $resultLrc;
        $r = html_entity_decode($resultLrc, ENT_QUOTES | ENT_HTML5);
        return $r;
    }

    // Comparator that determines which matches better
    private function cmp($lhs, $rhs) {
        $scoreArtistL = $this->getStringSimilarity($this->mArtist, $lhs['artist']);
        $scoreArtistR = $this->getStringSimilarity($this->mArtist, $rhs['artist']);
        $scoreTitleL = $this->getStringSimilarity($this->mTitle, $lhs['title']);
        $scoreTitleR = $this->getStringSimilarity($this->mTitle, $rhs['title']);

        // printf("artist " . $lhs['artist'] . " vs " . $rhs['artist'] . " | " . $scoreArtistL . " vs " . $scoreArtistR . "</br>");
        // printf("title " . $lhs['title'] . " vs " . $rhs['title'] . " | " . $scoreTitleL . " vs " . $scoreTitleR. "</br>");

        return $scoreArtistR + $scoreTitleR - $scoreArtistL - $scoreTitleL;
    }

    /**
     * Gets similarity score of 0-100 between 2 strings, the bigger the score is, the more similarity.
     */
    private static function getStringSimilarity($lhs, $rhs) {
        similar_text($lhs, $rhs, $percent);
        return $percent;
    }

    private function getTimeFromTag($tag) {
        $min = substr($tag, 1, 2);
        $sec = substr($tag, 4, 2);
        $milli = substr($tag, 7, 2);
        return $milli + $sec * 100 + $min * 60 * 100;
    }

    private function processLrcLine($lrc) {
        $result = array();
        foreach (explode("\n", $lrc) as $line) {
            $key = substr($line, 0, 10);
            $value = substr($line, 10, strlen($line) - 10);
            if (!$this->isValidLrcTime($key)) {
                $key = "";
                $value = $line;
            }
            array_push($result, array(
                'tag' => $key,
                'lrc' => $value
            ));
        }
        return $result;
    }

    private function isValidLrcTime($str) {
        if ($this->isNullOrEmptyString($str) || strlen($str) != 10 || $str[0] !== "[" || $str[9] != "]") {
            return FALSE;
        }
        for ($count = 1; $count < 9; $count++) {
            $ch = $str[$count];
            if ($ch !== ":" && $ch !== "." && !is_numeric($ch)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    // Function for basic field validation (present and neither empty nor only white space
    private static function isNullOrEmptyString($question){
        return (!isset($question) || trim($question)==='');
    }

    ///////////////////////////// Netease API ///////////////////////////////////////

    /**
     * Searches for a song based on title.
     */
    private static function search($word) {
        $params = array(
            'w' => $word,
            'format' => 'json',
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => "https://c.y.qq.com/soso/fcgi-bin/client_search_cp?".http_build_query($params)
        ));

        $output = curl_exec($curl);
      
        curl_close($curl);

        return $output;
    }

    /**
     * Downloads a lyric for a given music_id.
     */
    private static function download($music_id) {
        $url = "https://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg?nobase64=1&g_tk=5381&musicid=" . $music_id . "&format=json";
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array(
                "Referer:http://y.qq.com/portal/song/002OrhQA0bNYFg.html"
            ),
            CURLOPT_RETURNTRANSFER => true
        ));
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
} // End of class


////////////////////////// DEBUG ////////////////////////////////////

if (DEBUG == true) {
   class TestObj {
        private $items;

        function __construct() {
            $this->items = array();
        }

        public function addLyrics($lyric, $id) {
            printf("</br>");
            printf("song id: %s\n", $id);
            printf("</br>");
            printf("== lyric ==\n");
            printf("%s\n", $lyric);
            printf("** END of lyric **\n\n");
        }

        public function addTrackInfoToList($artist, $title, $id, $prefix) {
            printf("</br>");
            printf("song id: %s\n", $id);
            printf("artist [%s]\n", $artist);
            printf("title [%s]\n", $title);
            printf("prefix [%s]\n", $prefix);
            printf("</br>");

            array_push($this->items, array(
                'artist' => $artist,
                'title'  => $title,
                'id'     => $id
            ));
        }

        function getItems() {
            return $this->items;
        }

        function getFirstItem() {
            if (count($this->items) > 0) {
                return $this->items[0];
            } else {
                return NULL;
            }
        }
    }

    /**
     * Main
     */
    $title = "be my forever";
    $artist = "";
    echo "Trying to find lyrics for ['$title'] by artist ['$artist'] ...</br>";

    $testObj = new TestObj();
    $downloader = (new ReflectionClass("ZainQQLrc"))->newInstance();
    $count = $downloader->getLyricsList($artist, $title, $testObj);
    if ($count > 0) {
        $item = $testObj->getFirstItem();
        // var_dump($item);
        if (array_key_exists('id', $item)) {
            $downloader->getLyrics($item['id'], $testObj);
        } else {
            echo "\nno id to query lyric\n";
        }
    } else {
        echo " ****************************\n";
        echo " *** Failed to find lyric ***\n";
        echo " ****************************\n";
    }
}


