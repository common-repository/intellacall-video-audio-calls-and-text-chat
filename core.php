<?php

/**
 * Plugin Name:       Intellacall – Video, Audio calls and text chat
 * Plugin URI:        https://intellacall.com
 * Description:       Put your team in a face to face online interaction with the customer instantly from your existing webpage.
 * Version:           1.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Intellacall
 * Author URI:        https://intellacall.com/team
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       Intellacall-video-audio-calls-and-text-chat
 */

function intellacall_settings_init()
{
    // register a new setting for "intellacall" page
    register_setting('intellacall', 'intellacall_options');
    register_setting('intellacall', 'intellacall_button_options');

    // register a new section in the "intellacall" page
    add_settings_section(
        'intellacall_section_developers',
        __('', 'intellacall'),
        'intellacall_section_developers_cb', 'intellacall');

    // register a new field in the "intellacall_section_developers" section, inside the "intellacall" page
    add_settings_field(
        'intellacall_field_html', // as of WP 4.6 this value is used only internally
        // use $args' label_for to populate the id inside the callback
        __('', 'intellacall'),
        'intellacall_field_html_cb',
        'intellacall',
        'intellacall_section_developers',
        [
            'label_for' => 'intellacall_field_html',
            'class' => 'intellacall_row',
        ]
    );
}

/**
 * register our intellacall_settings_init to the admin_init action hook
 */
add_action('admin_init', 'intellacall_settings_init');

/**
 * custom option and settings:
 * callback functions
 */

// developers section cb

// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function intellacall_section_developers_cb($args)
{


}


// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function intellacall_field_html_cb($args)
{
    // get the value of the setting we've registered with register_setting()

}

/**
 * top level menu
 */
function intellacall_options_page()
{
    // add top level menu page
    //get current file path
    add_menu_page(
        'intellacall',
        'Intellacall Options',
        'manage_options',
        'intellacall',
        'intellacall_options_page_html',
        plugins_url( '03cc0e24-6b36-4629-8693-ecec6c8583fb-tst.png', __FILE__ )
    );
}

/**
 * register our intellacall_options_page to the admin_menu action hook
 */
add_action('admin_menu', 'intellacall_options_page');

/**
 * top level menu:
 * callback functions
 */
function intellacall_options_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // add error/update messages

    // check if the user have submitted the settings
    // wordpress will add the "settings-updated" $_GET parameter to the url
    if (isset($_GET['settings-updated'])) {
        // add settings saved message with the class of "updated" --> Commented bcs of the flow of
        //add_settings_error('intellacall_messages', 'intellacall_message', __('Settings Saved', 'intellacall'), 'updated');
    }

    // show error/update messages
    settings_errors('intellacall_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html("Welcome to Intellacall wordpress plug-in!"); ?></h1>
        <form action="options.php" autocomplete="off" method="post">


            <?php
            $options = get_option('intellacall_options');


            function blankRequest()
            {
                $response = wp_remote_get('https://app.intellacall.com/user/blank');
                $auth_token = $response['headers']['x-Auth-token'];
                $cookies = wp_remote_retrieve_cookies($response);
                return array('auth_token' => $auth_token, 'cookie' => $cookies);
            }

            function registerAppRequest()
            {
                $response = blankRequest();
                $auth_token = $response['auth_token'];
                $params = array('app' => 'ios', 'app_version' => '1.0', 'device_info' => json_encode(array('device' => 'wp_plugin', 'model' => 'wp_plugin', 'idfv' => '1000', 'os' => 'wp_plugin', 'os_version' => '1.0')));
                $appinstallId_response = wp_remote_post('https://app.intellacall.com/register_app.json', array(
                        'headers' => array('X-Auth-Token' => $auth_token),
                        'body' => $params,
                    )
                );
                $appinstallId = json_decode($appinstallId_response['body'])->app_install_id;
                $options['app_install_id'] = $appinstallId;
                update_option('intellacall_options', $options);

                return $appinstallId;
            }

            function signInRequest($email, $pass)
            {
                $options = get_option('intellacall_options');
                if (!array_key_exists('app_install_id',$options)) {
                    $appinstallId = registerAppRequest();
                } else {
                    $appinstallId = $options['app_install_id'];
                }

                $response = blankRequest();
                $auth_token = $response['auth_token'];
                $cookies = $response['cookie'];
                if ($appinstallId) {
                    $json = json_encode(array(
                        'app_install_id' => $appinstallId,
                        'user' => array(
                            'email' => $email,
                            'password' => $pass
                        ),
                    ));
                    $response = wp_remote_post('https://app.intellacall.com/user/sign_in.json', array(
                        'headers' => array(
                            'X-CSRF-Token' => $auth_token,
                            'Content-Type' => 'application/json; charset=utf-8',
                            'Cookie' => $cookies[0]->name . '=' . $cookies[0]->value,
                        ),
                        'body' => $json,
                    ));

                    if (!is_wp_error($response)) {
                        // The request went through successfully, check the response code against
                        // what we're expecting
                        if (201 == wp_remote_retrieve_response_code($response)) {
                            // Do something with the response
                            //ignore body nothing useful for plugin
                            //$body = wp_remote_retrieve_body( $response );

                            $cookies = wp_remote_retrieve_cookies($response);
                            $options = get_option('intellacall_options');
                            $options['login_cookie'] = $cookies[0]->name . '=' . $cookies[0]->value;
                            update_option('intellacall_options', $options);
                            return $cookies[0]->name . '=' . $cookies[0]->value;

                        } else {
                            echo "<p class='submit' style='font-weight: bold;color: red;'>".json_decode($response['body'], true)['error']."</p>";
                            return "";
                            // The response code was not what we were expecting, record the message
                        }
                    } else {
                        // There was an error making the request
                        $error_message = $response->get_error_message();
                        echo $error_message . "<br>";
                        return "";
                    }
                } else {
                    //there is no app install id!
                    echo "Something went wrong!";
                }
            }

            function getButtons($login_cookie)
            {
                $response = blankRequest();
                $auth_token = $response['auth_token'];
                $button_request_response = wp_remote_get('https://app.intellacall.com/mobile/button_styles.json', array(
                    'headers' => array(
                        'X-CSRF-Token' => $auth_token,
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Cookie' => $login_cookie,
                    ),
                ));
                return $button_request_response;
            }

            function saveButtonResult($button_request_response)
            {
                if (401 == wp_remote_retrieve_response_code($button_request_response)) {
                    $error_message = json_decode($button_request_response['body'])->error;
                    echo $error_message . '<br><button type="submit" class="btn btn-primary">Logout</button>';
                } else if (200 == wp_remote_retrieve_response_code($button_request_response)) {
                    $buttons = json_decode($button_request_response['body']);
                    $options = get_option('intellacall_options');
                    $options['buttons'] = $buttons->data;
                    update_option('intellacall_options', $options);
                    //echo "<pre>";
                    //print_r($buttons->data);
                    //echo "</pre>";
                    ?>
                    <p class="submit">
                        <input type="submit" name="submit" id="refresh-button" class="button button-primary" value="Refresh button list">
                    </p>
                    <style>
                        .intellacall-button-table {
                            font-family: arial, sans-serif;
                            border-collapse: collapse;
                            width: 80%;
                        }

                        .intellacall-td, .intellacall-th {
                            border: 1px solid #dddddd;
                            text-align: left;
                            padding: 8px;
                        }

                        tr:nth-child(even) {
                            background-color: #dddddd;
                        }
                        .intellacall-login-area {
                            display: none;
                        }
                    </style>

                    <table class="intellacall-button-table">

                        <tr>
                            <th class="intellacall-th">Name</th>
                            <th class="intellacall-th">Icon</th>
                            <th class="intellacall-th">Position</th>
                            <th class="intellacall-th">Tracker</th>
                            <th class="intellacall-th">Expandable?</th>
                            <th class="intellacall-th">Shortcode</th>
                            <th class="intellacall-th">Active on all pages?</th>

                        </tr>

                        <?foreach ($buttons->data as $button) { ?>
                            <tr>
                                <td class="intellacall-td"><? echo $button->name; ?></td>

                                <td class="intellacall-td"><? echo $button->btn_style->label; ?></td>

                                <td class="intellacall-td"><? echo $button->btn_position->label; ?></td>

                                <td class="intellacall-td"><? echo ($button->tracker) ? $button->tracker->label : ""; ?></td>

                                <td class="intellacall-td"><? echo ($button->is_expandable) ? "Yes" : "No"; ?></td>

                                <td class="intellacall-td"><code>[intellacall_button id="<? echo $button->id;?>"]</code></td>
                                <td class="intellacall-td">
                                    <? if ($button->btn_position->value == 1) {
                                        echo "Not available for inline buttons";
                                    } else {
                                        if ($options['active_button'] == $button->id) {
                                            echo "<button type='submit' value='".$button->id."' class='deactivate-button' name='intellacall_options[active_button]'>Deactivate </button>";
                                        } else {
                                            echo "<button type='submit' value='".$button->id."' class='activate-button' name='intellacall_options[active_button]'>Activate </button>";
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                        <? } ?>
                    </table>
                    <p class="submit">To activate a button on all pages click <span style="font-weight: bold;">"Activate"</span>. Only one button can be active at a time.<br>
                        To embed a button on a specific location, simply copy the short code and paste it on the page.</p>
                    <a target="_blank" href="https://app.intellacall.com">Click here to edit your buttons</a>
                    <p>Note: Editing an active button may result in errors.</p>

                    <button type="submit" class="btn btn-primary">Logout</button>
                    <p>Logging out will deactivate all your active buttons.</p>
                    <script>
                        (function($) {
                            $( ".activate-button" ).click(function(e) {
                                var button_id = $(this).val();
                                $("form").append('<input type="hidden" value="<? echo $options["app_install_id"] ?>" name="intellacall_options[app_install_id]">');
                                $("form").append('<input type="hidden" value="<? echo $options["login_cookie"] ?>" name="intellacall_options[login_cookie]">');
                                $("form").append('<input type="hidden" value="<? echo $options["buttons"] ?>" name="intellacall_options[buttons]">');
                                $("form").append('<input type="hidden" value='+button_id+' name="intellacall_options[active_button]">');
                            });

                            $( ".deactivate-button" ).click(function(e) {
                                $("form").append('<input type="hidden" value="<? echo $options["app_install_id"] ?>" name="intellacall_options[app_install_id]">');
                                $("form").append('<input type="hidden" value="<? echo $options["login_cookie"] ?>" name="intellacall_options[login_cookie]">');
                                $("form").append('<input type="hidden" value="<? echo $options["buttons"] ?>" name="intellacall_options[buttons]">');
                                $("form").append('<input type="hidden" value="0" name="intellacall_options[active_button]">');
                            });

                            $("#refresh-button").click(function (e) {
                                e.preventDefault();
                                location.reload();
                            });
                        })(jQuery);

                    </script>

                    <?
                } else {
                    //something else happened
                    $error_message = $button_request_response->get_error_message();
                    echo $error_message . "<br>";
                }
            }


            if (!array_key_exists('login_cookie',$options) || !array_key_exists('app_install_id',$options)) {
                //show login form

                ?>
                <div class="intellacall-login-area">
                    <p id="intellacall-login-subtext"><?php esc_html_e('To continue, please login to your Intellacall account:', 'intellacall'); ?></p>

                    <label for="email"><?php esc_html_e('Email: ', 'intellacall'); ?></label>
                    <input style="margin-left: 25px;" type="text"  placeholder="Intellacall email" value="<?php echo isset($options['email']) ? $options['email'] : (''); ?>" autocomplete="off" id="email" name="intellacall_options[email]"><br><br>
                    <label for="password"><?php esc_html_e('Password: ', 'intellacall'); ?></label>
                    <input type="password" id="pass" placeholder="Intellacall password" value="<?php echo isset($options['pass']) ? $options['pass'] : (''); ?>" name="intellacall_options[pass]"><br><br>
                    <?

                    if (array_key_exists('email',$options) && array_key_exists('pass',$options)) {
                        if ($options['email'] != null && $options['pass'] != null) {
                            //password and email removing after loged-in to the system
                            $login_cookie = signInRequest($options['email'], $options['pass']);
                            if ($login_cookie != "") {
                                //If login successful get user's buttons from server and show in dashboard
                                $buttons = getButtons($login_cookie);
                                //save buttons for future usage and show to the db
                                saveButtonResult($buttons);
                            }
                        }
                    }
                    submit_button('Login');
                    ?>
                    <a style="cursor: pointer;" href="https://app.intellacall.com/user/account/password/new" target="_blank" >Forgot your password?</a><br><br>
                    <a style="cursor: pointer;" href="https://intellacall.com/" target="_blank">Don't have an account?</a>
                </div>
                <?php
            } else {
                //show button list
            }

            // output security fields for the registered setting "intellacall"
            settings_fields('intellacall');

            //get updated options
            $options = get_option('intellacall_options');
            // check if login_cookie and app install id exist in the system, if so get the buttons and save to the system for future usage
            if (array_key_exists('login_cookie',$options) && array_key_exists('app_install_id',$options)) {
                $cookie = $options['login_cookie'];
                $button_request_response = getButtons($cookie);
                saveButtonResult($button_request_response);
            }

            ?>
        </form>

    </div>
    <?php
}

function intellacall_button_shortcode( $attributes ) {

    $options = get_option('intellacall_options');
    $buttons = $options["buttons"];
    foreach ($buttons as $button) {
        if ($button->id == $attributes["id"]) {
            return $button->embed_codes[0]->code;
        }
    }
}

function intellacall_shortcodes_init()
{
    add_shortcode('intellacall_button', 'intellacall_button_shortcode');
}

add_action('init', 'intellacall_shortcodes_init');


function hook_intellacall_js()
{
    ?>
    <script>!function (d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s);
            js.id = id;
            js.src = "https://app.intellacall.com/button_loader.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, "script", "elevensight-11buttonjs"); </script>
    <?php
}

add_action('wp_head', 'hook_intellacall_js');


function append_intellacall_html_to_body()
{
    $options = get_option('intellacall_options');
    $button_id = $options['active_button'];
    if ($button_id != 0) {
        $buttons = $options['buttons'];
        foreach ($buttons as $button) {
            if ($button->id == $button_id && $button->btn_position->value != 1) {
                echo $button->embed_codes[0]->code;
                break;
            }
        }
    }
}

add_action('wp_footer', 'append_intellacall_html_to_body');