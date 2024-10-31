<?php
/*
Plugin Name: PostLove
Version: 0.1.2
Plugin URI: http://www.eyeonsilicon.co.uk/postlove
Description: Monitors posts for hits to calculate which posts are most popular.
Author: Steve Collison
Author URI: http://www.stevecollison.co.uk
*/

  if (!function_exists('initPostLove')) {

    function initPostLove() {
      global $postlove,$table_prefix;

      // alter these variables to customise your copy of postlove.
      // **note** i plan to incorporate these options into the widget,
      // but do not know how to do this yet!

      /* if you don't mind, i'd like to know about blogs that use postlove.
      /  so i've added an automatic callback function that is executed
      /  when the plugin is activated. if for some reason you don't want me to
      /  know you're using the plugin, you can set the following to false.
      /  notes:
      /  * having this option on will not impact your blog's performance.
      /  * the only information sent will be your blog's url.
      */
      $postlove['notify_postlove_author']=true;

      // name of the table that will be automatically created when
      // you activate postlove. it will be used to store hit statistics.
      $postlove['table']=$table_prefix.'postlove';

      // the number of posts that will be displayed in the widget.
      $postlove['widget_posts']=10;

      // how many days of stats should be taken into consideration
      // when calculating post popularity.
      $postlove['stat_range']=30;

      // break in time before postlove starts monitoring a post for hits.
      // this number must be in hours. (ie. for 1 day enter 24)
      $postlove['breakin_time']=24*3;

      // if equalise is set to true, postlove will equalise the hits of posts
      // that lack enough days of statistics to give a realistic count.
      // for example, if your stat_range was 30, and a post only had 15 days of
      // statistics, the strength of the hits would be doubled when calculated.
      $postlove['equalise']=true;

      // don't record stats for users in the following array. (useful if you
      // browse your own blog lots...)
      // example: $postlove['ignore_user']=array('Steve','Kelly','Charli');
      $postlove['ignore_user']=array();

      // this is the title displayed for the widget.
      $postlove['widget_title']='Most Popular Posts';

      // if the reader is viewing a single post, we can remove that post
      // from the widget.
      $postlove['hide_current_post']=true;
    }

    function countPostLoveHit() {
      global $posts,$postlove,$wpdb,$current_user;

      // are we viewing a single post?
      if (is_single()) {

        // yes, we are! we should make sure that the current user viewing
        // this post isn't in the ignore user list. we don't want to mess up
        // those beautiful stats. ;]
        $ok=true;
        $ignore=$postlove['ignore_user'];
        foreach ($ignore as $value) {
          echo $value.'<br>';
          if ($current_user->user_login==$value)
            $ok=false;
        }

        if ($ok) {
          // yep, we're good to go... now let's check the current time against
          // the time the post was published, and work out the difference.
          $query='SELECT UNIX_TIMESTAMP()-UNIX_TIMESTAMP("'.$posts[0]->post_date
            .'")';
          $result=mysql_query($query,$wpdb->dbh);
          $row=mysql_fetch_row($result);
          mysql_free_result($result);

          // if the difference in time is greater than the specified break in
          // time, we can record statistics. otherwise, better luck next time.
          if ($row[0]>=$postlove['breakin_time']*60*60) {
            // update today's record for this post.
            $query='UPDATE `'.$postlove['table'].'` SET post_hits=post_hits+1 '
              .'WHERE log_date=CURDATE() AND post_ID='.$posts[0]->ID;
            mysql_query($query,$wpdb->dbh);
            if (mysql_affected_rows($wpdb->dbh)==0) {
              // if no rows were affected, today's record hasn't been created.
              // so let's create it!
              $query='INSERT INTO `'.$postlove['table'].'` (log_date,post_ID,'
                .'post_hits) VALUES (CURDATE(),'.$posts[0]->ID.',1)';
              mysql_query($query,$wpdb->dbh);
            }
          }
        }
      }
    }

    // this function is executed when you activate postlove.
    function installPostLove() {
      global $postlove,$wpdb;

      $db=$wpdb->dbh;

      $siteurl=get_option('siteurl');
      $url='http://www.eyeonsilicon.co.uk/api/postlove_notify.php?url='
        .urlencode($siteurl).'&auth='.md5($siteurl);
      $t=file_get_contents($url);
      // this sends your blog's url to the author of postlove.
      // you can turn this off in the initPostLove function.
      if ($postlove['notify_postlove_author']) {
        $url='http://www.eyeonsilicon.co.uk/api/postlove_notify.php?url='
          .urlencode(get_option('siteurl')).'&auth='.md5(get_option('siteurl'));
        @file_get_contents($url);
      }

      // check to see if the postlove table already exists.
      $table=$postlove['table'];
      $table_exists=false;
      $query='SHOW TABLES';
      $result=mysql_query($query,$wpdb->dbh);
      while ($row=mysql_fetch_row($result)) {
        if ($row[0]==$table)
          // found it!
          $table_exists=true;
      }
      mysql_free_result($result);

      // if the table doesn't exist...
      if (!$table_exists) {
        // create it!
        $query='CREATE TABLE  `'.$postlove['table'].'` (
`log_date` DATE NOT NULL ,
`post_ID` BIGINT UNSIGNED NOT NULL ,
`post_hits` INT UNSIGNED NOT NULL ,
PRIMARY KEY (  `log_date` ,  `post_ID` )
)';
        if (!mysql_query($query,$wpdb->dbh))
          // if the query failed, we need to trigger an error for the admin.
          trigger_error('PostLove could not create its database table.',E_USER_ERROR);

      }
    }

    function displayPostLove() {
      // this isn't actually used yet. feel free to hack away!
    }

    function widget_postlove($args) {
      // let's display a widget. :)
      global $wpdb,$postlove,$table_prefix,$posts;
      extract($args);
?>
<?php echo $before_widget; ?>
<?php echo $before_title
. $postlove['widget_title']
. $after_title; ?>
<ul>
<?php

  // let's start building the query.
  $query='SELECT post_ID,'.$table_prefix.'posts.post_title';

  // other query options we'll add on later...
  $queryext='';
  if (($postlove['hide_current_post']) AND (is_single()))
    $queryext.=' AND post_ID<>'.$posts[0]->ID.' ';

  // if equalise is true, all post statistics will be measured equally.
  // otherwise, relatively new posts won't carry equal weight in the
  // calculation.
  if ($postlove['equalise'])
    $query.=',SUM(post_hits) AS total_hits';
  else
    $query.=',(SUM(post_hits)/COUNT(*))*'.$postlove['stat_range'].' AS total_hits';

  // finish query.
  $query.=' FROM `'.$postlove['table'].'` LEFT JOIN `'.$table_prefix
    .'posts` ON '.$table_prefix.'posts.ID='.$postlove['table']
    .'.post_ID WHERE log_date>DATE_SUB(CURDATE(),INTERVAL '
    .$postlove['stat_range'].' DAY) '.$queryext.' GROUP BY post_ID ORDER BY '
    .'total_hits desc LIMIT '.$postlove['widget_posts'];

  // execute the query and display the results.
  $result=mysql_query($query,$wpdb->dbh);
  while ($row=mysql_fetch_row($result)) {
?>
<li><a href="<?php echo get_permalink($row[0]); ?>"><?php echo $row[1]; ?></a></li>
<?php
  }
?>
</ul>
<?php echo $after_widget; ?>
<?php
    }

    function initPostLoveWidget() {
      register_sidebar_widget('PostLove','widget_postlove');
    }

    add_filter('shutdown', 'countPostLoveHit');
    add_filter('wp_meta', 'displayPostLove');
    add_filter('widgets_init','initPostLoveWidget');
    register_activation_hook(__FILE__, 'installPostLove');

    initPostLove();

  }

  //installPostLove();
  //exit('..');

?>