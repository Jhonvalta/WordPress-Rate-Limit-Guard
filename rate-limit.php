<?php
/**
 * Plugin Name: Rate Limit Guard
 * Description: This plugin allows you to have a simple rate limit option to protect the site against DDoS or brute force attacks.
 * Version: 1.0
 * Author: Co Stresser
 * License: GPL2
 */

add_action('admin_menu', 'rtlimguard_menu');
function rtlimguard_menu()
{
    add_menu_page('RateLimit Guard', 'RateLimit Guard', 'manage_options', 'rate_limit_guard', 'rtlimguard_init', 'dashicons-shield');
}

if (!class_exists('WP_List_Table'))
{
    require_once (ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class rtlimguard_blockedip_table_list extends WP_List_Table
{

    function __construct()
    {
        global $status, $page;

        parent::__construct(array(
            'singular' => 'Rate Limit CO',
            'plural' => 'Rate Limit CO',
        ));
    }
    function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    function get_columns()
    {
        $columns = array(
            'ip' => __('IP', 'rate_limit_guard') ,
            'count' => __('How many times blocked?', 'rate_limit_guard') ,
            'date' => __('Date', 'rate_limit_guard') ,
        );
        return $columns;
    }
    protected function display_tablenav($which)
    {
    } // Remove navigation
    function column_date($item)
    {
        return date('Y-m-d h:i:s', $item['date']);
    }
    function column_count($item)
    {
        return $item['count'] . ' Request(s) blocked';
    }
    function get_sortable_columns()
    {
        $sortable_columns = array(
            'ip' => array(
                'ip',
                false
            ) ,
            'count' => array(
                'count',
                false
            ) ,
            'date' => array(
                'date',
                true
            ) ,
        );
        return $sortable_columns;
    }

    function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ratelimit';
        $per_page = 50;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array(
            $columns,
            $hidden,
            $sortable
        );
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->base_prefix}ratelimit");
        $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged'] - 1) * $per_page) : 0;
        $paged = sanitize_text_field($paged);
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'date';
        $orderby = sanitize_text_field($orderby);
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array(
            'asc',
            'desc'
        ))) ? $_REQUEST['order'] : 'desc';
        $order = sanitize_text_field($order);
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->base_prefix}ratelimit ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged) , ARRAY_A);
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
}
function rtlimguard_init()
{

    echo wp_kses_post('
<div class="wrap">
<img border="0" src="' . plugins_url('images/banner-772x250.png', __FILE__) . '" width="772" height="250"><br>
<link rel="stylesheet" type="text/css" href="' . plugins_url('style.css', __FILE__) . '">');
    if (!class_exists('Redis') and !class_exists('Memcached'))
    {
        echo wp_kses_post('<div align="left" class="notice inline notice-error notice-alt">
        <p><h2>Important Warning</h1>
 <h2>You need to Active "Redis" or "Memcached" in PHP extension for activation.</h2></p>
    </div>');
    }
    if (isset($_GET['action']))
    {
        global $wpdb;
        if ($_GET['action'] == "flush")
        {

            $wpdb->query("TRUNCATE TABLE {$wpdb->base_prefix}ratelimit");

            echo wp_kses_post('<div align="left" class="notice inline notice-success notice-alt">
<p><b>Success: </b> 
 All log Removed.</p>
    </div>
	');

            $ratlimco_inlinescript = "
<script>
setTimeout(function(){
window.location.href='admin.php?page=rate_limit_guard'; 
}, 3000); 
</script>";

            echo wp_kses($ratlimco_inlinescript, array(
                'script' => array() ,
            ));
        }
        elseif ($_GET['action'] == "submit" && !empty($_POST['req']))
        {
            $ratlimco_req = sanitize_text_field($_POST['req']);
            $ratlimco_sec = sanitize_text_field($_POST['sec']);
            $ratlimco_block = sanitize_text_field($_POST['block']);

            if (is_numeric($ratlimco_req) and is_numeric($ratlimco_sec) and is_numeric($ratlimco_block))
            {
                if ($ratlimco_req > 5 or $ratlimco_req < 20 or $ratlimco_sec > 10 or $ratlimco_sec < 20 or $ratlimco_block > 20 or $ratlimco_block < 3600)
                {

                    $rtlimguard_settings = array(
                        'req' => $ratlimco_req,
                        'seq' => $ratlimco_sec,
                        'block' => $ratlimco_block
                    );
                    update_option('rtlimguard_settings', $rtlimguard_settings);

                    //Sort RateLimit Plugin to reduce processing during block page display
                    $path = str_replace(WP_PLUGIN_DIR . '/', '', __FILE__);
                    if ($plugins = get_option('active_plugins'))
                    {
                        if ($key = array_search($path, $plugins))
                        {
                            array_splice($plugins, $key, 1);
                            array_unshift($plugins, $path);
                            update_option('active_plugins', $plugins);
                        }
                    }
                    //----
                    echo wp_kses_post('<div align="left" class="notice inline notice-success notice-alt">
<p><b>Success: </b> 
 All Data saved.</p>
    </div>

');
                }
                else
                {
                    echo wp_kses_post('<div align="left" class="notice inline notice-error notice-alt">
<p><b>Error: </b> 
 input error.</p>
</div>

');
                }
            }
            else
            {
                //---
                
            }
        }
    }
    echo wp_kses_post('

<h1>- Rate Limit Guard</h1>
<div align="left" class="notice inline notice-info notice-alt">

 <p>This plugin allows you to have a simple rate limit option to protect the site against DDoS or brute force attacks.</p>
 <p>- This plugin is only for guest users and does not check logged in users.</p>
 <p>- Cached pages may not be counted in the request calculation; It\'s not bad, always use a good  cache plugin.</p>
        </div>
        <div style="background:#ECECEC;border:1px solid #CCC;padding:0 10px;margin-top:5px;border-radius:5px;-moz-border-radius:5px;-webkit-border-radius:5px;">

<h1>- Settings</h1>
<h3>Low time interval and low number of requests in settings may cause unnecessary blocking.</h3></p>
');

    $rtlimguard_settings = get_option('rtlimguard_settings');
    $request = $rtlimguard_settings['req'];
    $period = $rtlimguard_settings['seq'];
    $called = $rtlimguard_settings['block'];

    $ratlimco_content_1 = '<form method="post" action="admin.php?page=rate_limit_guard&action=submit">

If an IP sends <input type="number" min="5" max="15" name="req" style="width: 5em;" value="' . $request . '"> requests in <input type="number" min="10" max="20" name="sec" style="width: 6em;" value="' . $period . '"> Seconds, block it for <input type="number" min="20" max="3600" name="block" style="width: 6em;" value="' . $called . '"> seconds.<br>
Default is 7 Request per 10 Seconds - Max Block time is 3600.';
    echo wp_kses($ratlimco_content_1, array(
        'input' => array(
            'type' => array() ,
            'min' => array() ,
            'max' => array() ,
            'name' => array() ,
            'style' => array() ,
            'value' => array() ,
        ) ,
        'form' => array(
            'method' => array() ,
            'action' => array() ,
        ) ,
        'br' => array() ,
    ));

    submit_button();
    echo wp_kses_post('
</form>
    </div>

<h1>- Statistics</h1>
<p>We don\'t block any IP! We only limit their requests according to your settings.</p>

	');

    global $wpdb;

    $table = new rtlimguard_blockedip_table_list();
    $table->prepare_items();

    echo wp_kses_post('
    <form id="persons-table" method="GET">
        <input type="hidden" name="page" value="' . $_REQUEST['page'] . '"/>
        ' . $table->display() . '
    </form>
  <a class="button button-secondary" href="admin.php?page=rate_limit_guard&action=flush" >Clear Log</a>
</div>');
}
//Create db
global $rtlimguard_version;
$rtlimguard_version = '1.0';
function rtlimguard_install()
{
    global $wpdb;
    global $rtlimguard_version;
    $table_ratelimit = $wpdb->prefix . "ratelimit";
    $charset_collate = $wpdb->get_charset_collate();
    $ratelimit = "CREATE TABLE IF NOT EXISTS $table_ratelimit (
		  id int(11) NOT NULL AUTO_INCREMENT,
		  ip varchar(50) NOT NULL,
		  count bigint(20) NOT NULL,
		  date bigint(20) NOT NULL,
		    PRIMARY KEY (id)
		  ) ENGINE=InnoDB $charset_collate;";
    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($ratelimit);
    add_option('rtlimguard_version', $rtlimguard_version);
    $rtlimguard_settings = array(
        'req' => 7,
        'seq' => 10,
        'block' => 20
    );
    add_option('rtlimguard_settings', $rtlimguard_settings);
}
register_activation_hook(__FILE__, 'rtlimguard_install');
function rtlimguard_deactivate()
{
    global $wpdb;

    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->base_prefix}ratelimit");

    delete_option('rtlimguard_version');
    delete_option('rtlimguard_settings');
}
register_deactivation_hook(__FILE__, 'rtlimguard_deactivate');
// END DB creator
function rtlimguard_guardstart()
{
    function rtlimguard_blocked($ip)
    {
        //Disable Cache for error page
        define('DONOTCACHEPAGE', true);
        define('DONOTCACHEDB', true);
        define('DONOTMINIFY', true);
        define('DONOTCACHEOBJECT', true);
        //----
        global $wpdb;
        $table_ratelimit = $wpdb->prefix . "ratelimit";

        $ratlimco_result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->base_prefix}ratelimit WHERE ip = '%s' limit 1", $ip));

        $ratlimco_rowcount = $wpdb->num_rows;
        $time = time();
        if ($ratlimco_rowcount > 0)
        {
            foreach ($ratlimco_result as $results)
            {
                $count = $results->count + 1;
                $id = $results->id;
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->base_prefix}ratelimit SET count = %d , date = %d WHERE  id = %d limit 1", $count, $time, $id));

            }
        }
        else
        {
            $wpdb->insert($table_ratelimit, array(
                'ip' => $ip,
                'count' => '1',
                'date' => $time,
            ));
        }
        echo wp_kses_post('<p style="text-align:center"><img border="0" src="' . plugins_url('images/banner-772x250.png', __FILE__) . '" width="772" height="250"></p>
<p style="text-align:center"><strong><span style="font-size:16px;font-family:Courier New,Courier">Your request (' . $ip . ')  is temporarily blocked! Wait a few seconds and then try again.</span></strong></p>
<p style="text-align:center">&nbsp;</p>
<p style="text-align:center"><span style="font-size:16px;font-family:Courier New,Courier">Powered by <a href="https://wordpress.org/plugins/rate-limit-guard/" target="_blank">Rate Limit Guard</a></span></p>
');
    }
    if (!is_user_logged_in())
    {

        $rtlimguard_settings = get_option('rtlimguard_settings');
        $rtlimguard_request = $rtlimguard_settings['req'];
        $rtlimguard_period = $rtlimguard_settings['seq'];
        $rtlimguard_called = $rtlimguard_settings['block'];

        $total_user_calls = 0;
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $user_ip_address = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $user_ip_address = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        }
        else
        {
            $user_ip_address = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        if (class_exists('Redis'))
        {
            $redis = new Redis();
            $redis->connect('localhost', 6379);
            if (!$redis->exists($user_ip_address))
            {
                $redis->set($user_ip_address, 1);
                $redis->expire($user_ip_address, $rtlimguard_period);
                $total_user_calls = 1;
            }
            else
            {
                $redis->INCR($user_ip_address);
                $total_user_calls = $redis->get($user_ip_address);
                if ($total_user_calls > $rtlimguard_request)
                {
                    rtlimguard_blocked($user_ip_address);
                    $redis->set($user_ip_address, $rtlimguard_request);
                    $redis->expire($user_ip_address, $rtlimguard_called);
                    exit();
                }
            }
        }
        elseif (class_exists('Memcached'))
        {
            $memc = new Memcached();
            $memc->addServer("localhost", 11211);
            $item = $memc->get($user_ip_address);
            if ($memc->getResultCode() == Memcached::RES_SUCCESS)
            {
                $total_user_calls = $memc->get($user_ip_address);
                $total_user_calls = $total_user_calls + 1;
                $memc->replace($user_ip_address, $total_user_calls);
                if ($total_user_calls > $rtlimguard_request)
                {
                    rtlimguard_blocked($user_ip_address);
                    $memc->set($user_ip_address, $rtlimguard_request, $rtlimguard_called);
                    exit();
                }
            }
            else
            {
                $memc->set($user_ip_address, 1, $rtlimguard_period);
            }
        }
    }
}
add_action('plugins_loaded', 'rtlimguard_guardstart');
