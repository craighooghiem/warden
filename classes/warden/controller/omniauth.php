<?php
/**
 * Warden: User authorization & authentication library for FuelPHP.
 *
 * @package    Warden
 * @subpackage Warden
 * @version    1.1
 * @author     Andrew Wayne <lifeandcoding@gmail.com>
 * @license    MIT License
 * @copyright  (c) 2011 - 2012 Andrew Wayne
 */
namespace Warden;

/**
 * Controller_OmniAuth
 *
 * Ported from NinjAuth
 *
 * @package    Warden
 * @subpackage OmniAuth
 *
 * @author Phil Sturgeon, Modified by Andrew Wayne
 */
class Controller_OmniAuth extends \Controller
{
    public function before()
    {
        parent::before();

        if (\Config::get('warden.omniauthable.in_use') !== true) {
            throw new \Request404Exception();
        }
    }

    public function action_session($provider)
    {
        OmniAuth_Strategy::forge($provider)->authenticate();
    }

    public function action_callback($provider)
    {
        $strategy = OmniAuth_Strategy::forge($provider);
        OmniAuth_Strategy::login_or_register($strategy);
    }

    public function action_register()
    {
        $user_hash  = \Session::get('omniauth');
        $user_hash || $user_hash = array();

        $profilable = (bool)(\Config::get('warden.profilable') === true);
        $full_name  = true;
        if ($profilable) {
            $full_name = \Input::post('full_name') ? : \Arr::get($user_hash, 'name');
        }

        $username  = \Input::post('username') ? : \Arr::get($user_hash, 'nickname');
        $email     = \Input::post('email') ? : \Arr::get($user_hash, 'email');
        $password  = \Input::post('password');

        $user = $service = null;

        if ($username && $full_name && $email && $password) {
            try {
                $user = new \Model_User(array(
                    'email'    => $email,
                    'username' => $username,
                    'password' => $password
                ));

                if ($profilable) {
                    $user->profile = new \Model_Profile(array(
                        'full_name' => $full_name
                    ));
                }

                $service = new \Model_Service(array(
                    'uid'           => $user_hash['credentials']['uid'],
                    'provider'      => $user_hash['credentials']['provider'],
                    'access_token'  => $user_hash['credentials']['token'],
                    'access_secret' => $user_hash['credentials']['secret']
                ));

                if (\Config::get('warden.omniauthable.link_multiple') === true) {
                    $user->services[] = $service;
                } else {
                    $user->service = $service;
                }

                $user->save();

            } catch (\Exception $ex) {
                \Session::set_flash('warden.omniauthable.error', $ex->getMessage());
                goto display;
            }

            \Response::redirect(\Config::get('warden.omniauthable.urls.registered'));
        }

        display:

        $this->response->body = \View::forge('warden/omniauth/register', array(
            'user' => (object)compact('username', 'full_name', 'email', 'password')
        ), false);
    }
}