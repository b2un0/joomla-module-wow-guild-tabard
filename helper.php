<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

abstract class ModWowGuildTabardHelper
{
    public static function getAjax()
    {
        $module = JModuleHelper::getModule('mod_' . JFactory::getApplication()->input->get('module'));

        if (empty($module)) {
            return false;
        }

        JFactory::getLanguage()->load($module->module);

        $params = new JRegistry($module->params);
        $params->set('ajax', 0);

        ob_start();

        require(dirname(__FILE__) . '/' . $module->module . '.php');

        return ob_get_clean();
    }

    public static function getData(JRegistry &$params)
    {
        if ($params->get('ajax')) {
            return;
        }

        $params->set('guild', rawurlencode(JString::strtolower($params->get('guild'))));
        $params->set('realm', rawurlencode(JString::strtolower($params->get('realm'))));
        $params->set('region', strtolower($params->get('region')));

        $url = 'http://' . $params->get('region') . '.battle.net/api/wow/guild/' . $params->get('realm') . '/' . $params->get('guild');

        $cache = JFactory::getCache('wow', 'output');
        $cache->setCaching(1);
        $cache->setLifeTime($params->get('cache_time', 60));

        $key = md5($url);

        if (!$result = $cache->get($key)) {
            try {
                $http = JHttpFactory::getHttp();
                $http->setOption('userAgent', 'Joomla! ' . JVERSION . '; WoW Guild Tabard; php/' . phpversion());

                $result = $http->get($url, null, $params->get('timeout', 10));
            } catch (Exception $e) {
                return $e->getMessage();
            }

            $cache->store($result, $key);
        }

        if ($result->code != 200) {
            return __CLASS__ . ' HTTP-Status ' . JHtml::_('link', 'http://wikipedia.org/wiki/List_of_HTTP_status_codes#' . $result->code, $result->code, array('target' => '_blank'));
        }

        $result->body = json_decode($result->body);

        $tabard = new stdClass;
        $tabard->ring = ($result->body->side == 1) ? 'horde' : 'alliance';
        $tabard->staticUrl = JUri::base(true) . '/modules/mod_wow_guild_tabard/tmpl/images/';
        $tabard->emblem = self::AlphaHexToRGB($result->body->emblem->iconColor, $result->body->emblem->icon);
        $tabard->border = self::AlphaHexToRGB($result->body->emblem->borderColor, $result->body->emblem->border);
        $tabard->bg = self::AlphaHexToRGB($result->body->emblem->backgroundColor, 0);
        return $tabard;
    }

    private static function AlphaHexToRGB($color, $first)
    {
        $array = array_map('hexdec', str_split($color, 2));
        $array[0] = $first; // override alpha value
        return $array;
    }
}