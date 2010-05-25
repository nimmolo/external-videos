<?

/// ***   Pulling Videos From Diverse Sites   *** ///


function fetch_youtube_videos($author_id)
{

    $url = "http://gdata.youtube.com/feeds/api/users/$author_id/uploads/";
    $date = date(DATE_RSS);
    $new_videos = 0;

    // loop through all feed pages
    while ($url != NULL) {
        $videofeed = fetch_feed($url);
        $length = $videofeed->get_item_quantity();
        $items = $videofeed->get_items(0,$length);

        for ($i = 0; $i < $length; $i++) {
            // media:group mediaRSS subpart
            $mediagroup = $items[$i]->get_enclosure();

            // extract fields
            $video = array();
            $video['host_id']     = 'youtube';
            $video['author_id']   = strtolower($author_id);
            $video['video_id']    = preg_replace('/http:\/\/gdata.youtube.com\/feeds\/api\/videos\//', '', $items[$i]->get_id());
            $video['title']       = $items[$i]->get_title();
            $video['description'] = $items[$i]->get_content();
            $video['authorname']  = $items[$i]->get_author()->get_name();
            $video['videourl']    = preg_replace('/\&amp;feature=youtube_gdata/','', $items[$i]->get_link());
            $video['published']   = date("Y-m-d H:i:s", strtotime($items[$i]->get_date()));
            $video['author_url']  = "http://www.youtube.com/user/".$video['author_id'];
            $video['category']    = $mediagroup->get_category()->get_label();
            $video['keywords']    = $mediagroup->get_keywords();
            $video['thumbnail']   = $mediagroup->get_thumbnail();
            $video['duration']    = $mediagroup->get_duration($convert = true);

            // save video
            $is_new = save_video($video);
            if ($is_new) {
                $new_videos++;
            }
        }

        // next feed page, if available
        $next_url = $videofeed->get_links($rel = 'next');
        $url = $next_url[0];
    }

    return $new_videos;
}


function fetch_vimeo_videos($author_id, $developer_key, $secret_key)
{
  $vimeo = new phpVimeo($developer_key, $secret_key);
  $per_page = 50;
  $date = date(DATE_RSS);
  $new_videos = 0;

  // loop through all feed pages
  $page = 1;
  do {
    // Do an authenticated call
    try {
      $videofeed = $vimeo->call('vimeo.videos.getUploaded',
                                array('user_id' => $author_id, 
                                      'full_response' => 'true',
                                      'page' => $page,
                                      'per_page' => $per_page), 
                                      'GET', 
                                phpVimeo::API_REST_URL, 
                                false, 
                                true);  
    }
    catch (VimeoAPIException $e) {
      echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
    }

    foreach ($videofeed->videos->video as $vid)
    {
      // extract fields
      $video = array();
      $video['host_id']     = 'vimeo';
      $video['author_id']   = strtolower($author_id);
      $video['video_id']    = $vid->id;
      $video['title']       = $vid->title;
      $video['description'] = $vid->description;
      $video['authorname']  = $vid->owner->display_name;
      $video['videourl']    = $vid->urls->url[0]->_content;
      $video['published']   = $vid->upload_date;
      $video['author_url']  = "http://www.vimeo.com/".$video['author_id'];
      $video['category']    = '';
      $video['keywords']    = array();
      if ($vid->tags) {
        foreach ($vid->tags->tag as $tag) {
          array_push($video['keywords'], $tag->_content);
        }
      }
      $video['thumbnail']   = $vid->thumbnails->thumbnail[0]->_content;
      $video['duration']    = sec2hms($vid->duration);

      // save video
      $is_new = save_video($video);
      if ($is_new) {
          $new_videos++;
      }
    }

    // next page
    $page += 1;
  } while ($videofeed->videos->on_this_page == $per_page);

  return $new_videos;
}

function fetch_dotsub_videos($author_id)
{

    $url = "http://dotsub.com/view/user/$author_id?page=0";
    $newlines = array("\t","\n","\r","\x20\x20","\0","\x0B",",");
    $date = date(DATE_RSS);
    $page=0;
    $new_videos = 0;

    // loop through all feed pages
    while ($url != NULL) {
        $html = file_get_html($url);
        
        $length_str = $html->find('div[class=pagercontext]',0);
        $length_pcs = explode(" ", str_replace($newlines, "",$length_str->plaintext));

        $length = $length_pcs[3]-$length_pcs[1]+1;
        $current = $length_pcs[3];
        $items = $length_pcs[5];

        for ($i = 0; $i < $length; $i++) {
            // get video item at position i
            $item = $html->find('div[class=mediaBox]',$i);
            $metadata = $item->find('div[class=mediaMetadata]',0);
            $next=$i+1;
            
            // extract fields
            $video = array();
            $video['host_id']     = 'dotsub';
            $video['author_id']   = strtolower($author_id);
            $video['video_id']    = $item->id;
            $video['title']       = $metadata->find('a',0)->plaintext;
            $video['description'] = $metadata->find('p[id=description'.$next.'p]',0)->innertext;
            $video['authorname']  = $author_id;
            $video['videourl']    = 'http://dotsub.com'.$metadata->find('a',0)->href;
            
            // need to retrieve videourl to gain upload date
            $html_video = file_get_html($video['videourl']);
            $published_div = $html_video->find('div[class=moduleBody]',0)->find('div',6);
            $published_pcs = explode(" ", str_replace($newlines, "",$published_div));
            $monthnames = array(1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
            $month = array_search($published_pcs[4],$monthnames);
            $published = mktime(0, 0, 0, $month, $published_pcs[5], $published_pcs[6]);
            $video['published']   = date("Y-m-d H:i:s", $published);

            // further fields
            $video['author_url']  = "http://www.youtube.com/user/".$video['author_id'];
            $video['category']    = $metadata->find('div[class=mediaLinks]',0)->find('a',2)->plaintext;
            $video['keywords']    = NULL;
            $video['thumbnail']   = 'http://dotsub.com'.$item->find('div[class=thumbnail]',0)->find('img',0)->src;
            
            // parse the seconds out of the duration string
            $duration_str = $metadata->find('li[class=first-metadataItem]',0)->find('h4',0)->plaintext;
            $duration_pcs = explode(" ", str_replace($newlines, "",$duration_str));
            $video['duration'] = $duration_pcs[1];

            // save video
            $is_new = save_video($video);
            if ($is_new) {
                $new_videos++;
            }
        }

        // next feed page, if available
        if ($current < $items) {
          $page = $page + 1;
          $url = "http://dotsub.com/view/user/$author_id?page=$page";
        } else {
          $url = NULL;
        }
    }
    return $new_videos;
}

?>