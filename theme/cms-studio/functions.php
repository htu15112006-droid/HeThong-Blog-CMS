<?php
/** Blog CMS Studio: WordPress data, protected administration and live observability. */
defined('ABSPATH') || exit;
add_filter('option_blogname', function () { return 'Hệ Thống Blog/ CMS'; });
add_action('after_setup_theme', function () {
    add_theme_support('title-tag'); add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption']);
});
add_filter('show_admin_bar', '__return_false');
add_filter('login_redirect', function ($redirect, $requested, $user) {
    if (!is_wp_error($user) && $user instanceof WP_User && !user_can($user, 'edit_posts') && strpos($requested, 'cms=dashboard') !== false) return home_url('/');
    return $redirect;
}, 10, 3);
function studio_url($view = '', $args = []) {
    return add_query_arg(array_merge($view ? ['cms' => $view] : [], $args), home_url('/'));
}
function studio_view() { return sanitize_key(wp_unslash($_GET['cms'] ?? '')); }
function studio_require($cap) { if (!current_user_can($cap)) wp_die('Bạn không có quyền thực hiện thao tác này.', 'Không được phép', ['response' => 403]); }
function studio_notice($text, $error = false) {
    set_transient('studio_notice_' . get_current_user_id(), ['text' => $text, 'error' => $error], 90);
}
add_action('template_redirect', function () {
    $view = studio_view();
    // wp_login_form does not set WordPress's login test cookie itself.
    if ($view === 'login' && !is_user_logged_in() && !headers_sent()) {
        setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        if (SITECOOKIEPATH !== COOKIEPATH) setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
    if ($view) { nocache_headers(); header('X-Robots-Tag: noindex, nofollow'); }
    if (in_array($view, ['dashboard','posts','edit','categories','users','database','monitoring','logs'], true)) {
        if (!is_user_logged_in()) { wp_safe_redirect(studio_url('login')); exit; }
        studio_require(in_array($view, ['users','database','monitoring','logs'], true) ? 'manage_options' : 'edit_posts');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['studio_action'])) return;
    studio_require('edit_posts');
    if (!isset($_POST['studio_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['studio_nonce'])), 'studio_write')) wp_die('Phiên thao tác hết hạn. Tải lại trang và thử lại.', '', ['response' => 403]);
    $action = sanitize_key(wp_unslash($_POST['studio_action']));
    $destination = 'posts'; $result = null;
    if ($action === 'save_post') {
        $id = absint($_POST['post_id'] ?? 0);
        if ($id && (!current_user_can('edit_post', $id) || get_post_type($id) !== 'post')) wp_die('Không được sửa bài này.', '', ['response' => 403]);
        $status = sanitize_key(wp_unslash($_POST['post_status'] ?? 'draft'));
        if (!in_array($status, ['draft','publish'], true)) $status = 'draft';
        if ($status === 'publish' && !current_user_can('publish_posts')) $status = 'draft';
        $title = sanitize_text_field(wp_unslash($_POST['post_title'] ?? ''));
        if (!$title) { studio_notice('Vui lòng nhập tiêu đề bài viết.', true); wp_safe_redirect(studio_url('edit', ['id' => $id])); exit; }
        $cats = array_filter(array_map('absint', (array)($_POST['categories'] ?? [])), function ($cat) { return term_exists($cat, 'category'); });
        $result = wp_insert_post(wp_slash(['ID'=>$id, 'post_type'=>'post', 'post_title'=>$title, 'post_content'=>wp_kses_post(wp_unslash($_POST['post_content'] ?? '')), 'post_excerpt'=>sanitize_textarea_field(wp_unslash($_POST['post_excerpt'] ?? '')), 'post_status'=>$status, 'post_category'=>$cats]), true);
    } elseif ($action === 'trash_post') {
        $id = absint($_POST['post_id'] ?? 0);
        if (!current_user_can('delete_post', $id) || get_post_type($id) !== 'post') wp_die('Không được xóa bài này.', '', ['response'=>403]);
        $result = wp_trash_post($id);
    } elseif ($action === 'save_category') {
        studio_require('manage_categories'); $destination = 'categories';
        $data=['description'=>sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''))];
        $name=sanitize_text_field(wp_unslash($_POST['name'] ?? ''));$id=absint($_POST['term_id'] ?? 0);
        if ($id) $result=wp_update_term($id,'category',array_merge($data,['name'=>$name]));
        else $result=wp_insert_term($name,'category',$data);
    } elseif ($action === 'delete_category') {
        studio_require('manage_categories');$destination='categories';$id=absint($_POST['term_id']??0);
        $result=$id==get_option('default_category') ? new WP_Error('default','Không thể xóa danh mục mặc định.') : wp_delete_term($id,'category');
    } elseif ($action === 'create_user') {
        studio_require('create_users');$destination='users';
        $role=sanitize_key(wp_unslash($_POST['role']??'subscriber'));
        if (!in_array($role,['author','editor','subscriber'],true)) $role='subscriber';
        $password=(string)wp_unslash($_POST['password']??'');
        $result=strlen($password)<12 ? new WP_Error('password','Mật khẩu cần ít nhất 12 ký tự.') : wp_insert_user(['user_login'=>sanitize_user(wp_unslash($_POST['username']??'')), 'user_email'=>sanitize_email(wp_unslash($_POST['email']??'')), 'display_name'=>sanitize_text_field(wp_unslash($_POST['display_name']??'')), 'user_pass'=>$password, 'role'=>$role]);
    } else wp_die('Thao tác không hợp lệ.', '', ['response'=>400]);
    studio_notice(is_wp_error($result) ? $result->get_error_message() : ($result ? 'Đã lưu thay đổi thành công.' : 'Không thể lưu thay đổi. Vui lòng thử lại.'), is_wp_error($result) || !$result);
    wp_safe_redirect(studio_url($destination));exit;
});
function studio_form_fields($action) { wp_nonce_field('studio_write','studio_nonce'); echo '<input type="hidden" name="studio_action" value="'.esc_attr($action).'">'; }
function studio_remote_json($url) {
    $response=wp_remote_get($url,['timeout'=>5,'redirection'=>0,'limit_response_size'=>524288]);
    if(is_wp_error($response)) return new WP_Error('upstream','Không kết nối được dịch vụ. Kiểm tra container và mạng Docker.');
    if(wp_remote_retrieve_response_code($response)!==200) return new WP_Error('upstream','Dịch vụ giám sát chưa sẵn sàng.');
    $json=json_decode(wp_remote_retrieve_body($response),true);
    return is_array($json) ? $json : new WP_Error('upstream','Dữ liệu trả về không hợp lệ.');
}
function studio_prom($q,$range=false) {
    $base='http://prometheus:9090/api/v1/';
    $args=['query'=>$q];
    if($range) $args+=['start'=>time()-900,'end'=>time(),'step'=>30];
    return studio_remote_json($base.($range?'query_range':'query').'?'.http_build_query($args));
}
add_action('rest_api_init',function(){
    register_rest_route('cms-studio/v1','/metrics',['methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options');},'callback'=>function(){
        $queries=['services'=>'up','mysql'=>'mysql_up','nginx'=>'nginx_up','cpu'=>'100 * (1 - avg(rate(node_cpu_seconds_total{mode="idle"}[5m])))','ram'=>'100 * (1 - node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes)','connections'=>'mysql_global_status_threads_connected','requests'=>'rate(nginx_http_requests_total[5m])'];
        $out=[];
        foreach($queries as $key=>$q){$r=studio_prom($q);$out[$key]=is_wp_error($r)?['error'=>$r->get_error_message()]:($r['data']['result']??[]);}
        $r=studio_prom($queries['requests'],true);$out['history']=is_wp_error($r)?[]:($r['data']['result']??[]);
        $out['updated']=gmdate('c');$res=rest_ensure_response($out);$res->header('Cache-Control','no-store');return $res;
    }]);
    register_rest_route('cms-studio/v1','/logs',['methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options');},'callback'=>function($req){
        // Fixed allowlist; never accept arbitrary upstream URL or arbitrary LogQL.
        $selector='{job="docker"} |~ "^[0-9a-fA-F:.]+ - .*HTTP/"';
        $mode=sanitize_key($req->get_param('mode')??'access');
        if(!in_array($mode,['access','errors','count'],true))return new WP_Error('mode','Bộ lọc không hợp lệ.',['status'=>400]);
        $q=$mode==='count'?'sum(count_over_time('.$selector.' [5m]))':$selector.($mode==='errors'?' |= " 404 "':'');
        $r=studio_remote_json('http://loki:3100/loki/api/v1/query_range?'.http_build_query(['query'=>$q,'start'=>(string)((time()-900)*1000000000),'end'=>(string)(time()*1000000000),'limit'=>80,'direction'=>'backward','step'=>'30']));
        if(is_wp_error($r))return new WP_Error('logs',$r->get_error_message(),['status'=>502]);
        $res=rest_ensure_response(['mode'=>$mode,'query'=>$q,'data'=>$r['data']??[]]);$res->header('Cache-Control','no-store');return $res;
    }]);
});
add_action('wp_enqueue_scripts',function(){
    wp_enqueue_style('cms-studio',get_stylesheet_uri(),[],'1.0.0');
    wp_enqueue_script('cms-studio',get_template_directory_uri().'/app.js',[],'1.0.0',true);
    wp_localize_script('cms-studio','CMS_STUDIO',['api'=>rest_url('cms-studio/v1/'),'nonce'=>wp_create_nonce('wp_rest')]);
});
