<?php
/**
 * Copyright 1999-2017. Parallels IP Holdings GmbH.
 */

/**
 * Class Modules_WelcomeWpo_Helper
 */
class Modules_WelcomeWp_Helper
{
    /**
     * Creates return URL from the global HTTP_REFERER variable but with validity check
     *
     * @return string
     */
    public static function getReturnUrl()
    {
        $url_global_generic = self::getGlobalUrl();

        if (!empty($_SERVER['HTTP_REFERER'])) {

            $url_global_return = $_SERVER['HTTP_REFERER'];
            $white_list = self::getWhiteListPages();

            foreach ($white_list as $item) {
                if ($url_global_generic.$item == $url_global_return) {
                    return $url_global_return;
                }
            }
        }

        return $url_global_generic;
    }

    /**
     * Determines global URL dependent of the OS for the return redirect
     *
     * @return string
     */
    private static function getGlobalUrl()
    {
        $server_scheme = self::getServerScheme();
        $server_host = self::getServerHost();

        return htmlspecialchars($server_scheme.'://'.$server_host);
    }

    /**
     * Retrieves the request scheme from the global server variable
     *
     * @return string
     */
    private static function getServerScheme()
    {
        if (!empty($_SERVER['REQUEST_SCHEME'])) {
            return $_SERVER['REQUEST_SCHEME'];
        }

        if (!empty($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] != 'on') {
            return 'http';
        }

        return 'https';
    }

    /**
     * Retrieves the server host from the global server variable
     *
     * @return string
     */
    private static function getServerHost()
    {
        if (!empty($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }

        if (pm_ProductInfo::isWindows()) {
            return $_SERVER['LOCAL_ADDR'].':'.$_SERVER['SERVER_PORT'];
        }

        return $_SERVER['SERVER_ADDR'].':'.$_SERVER['SERVER_PORT'];
    }

    /**
     * Returns all pages where the tutorial banner should be loaded
     *
     * @return array
     */
    public static function getWhiteListPages()
    {
        $white_list = array(
            '/admin/',
            '/admin/home?context=home',
            '/smb/',
            '/smb/web/view'
        );

        return $white_list;
    }

    /**
     * Checks whether extension is installed
     *
     * @param $name
     *
     * @return bool
     */
    public static function isInstalled($name)
    {
        return file_exists(dirname(pm_Context::getPlibDir()).'/'.$name);
    }

    /**
     * Creates the link to the Catalog page of the transmitted extension
     *
     * @param $name
     *
     * @return string
     */
    public static function getExtensionCatalogLink($name)
    {
        $catalog_id = self::getExtensionCatalogId($name);

        if (!empty($catalog_id)) {
            return '/admin/extension/catalog/package/'.$catalog_id;
        }

        return '/admin/extension/catalog';
    }

    /**
     * Returns the correct catalog ID of the transmitted extension ID
     *
     * @param string $id
     * @param bool   $array
     *
     * @return array|bool|mixed
     */
    public static function getExtensionCatalogId($id = '', $array = false)
    {
        $catalog_ids = array(
            'wp-toolkit'         => '00d002a7-3252-4996-8a08-aa1c89cf29f7',
            'panel-migrator'     => 'bebc4866-d171-45fb-91a6-4b139b8c9a1b',
            'security-advisor'   => '6bcc01cf-d7bb-4e6a-9db8-dd1826dcad8f',
            'pagespeed-insights' => '3d2639e6-64a9-43fe-a990-c873b6b3ec66'
        );

        if (!empty($catalog_ids[$id])) {
            return $catalog_ids[$id];
        }

        if (!empty($array)) {
            return $catalog_ids;
        }

        return false;
    }

    /**
     * Installs an extension from the catalog whitelist
     *
     * @param $id
     *
     * @return bool|string
     */
    public static function installExtension($id)
    {
        $catalog_id = self::getExtensionCatalogId($id);

        if (!empty($catalog_id)) {
            $url = 'https://ext.plesk.com/packages/'.$catalog_id.'-'.$id.'/download';

            try {
                self::installExtensionApi($url);
            }
            catch (Exception $e) {
                return $e->getMessage();
            }
        }

        return true;
    }

    /**
     * Executes the API-RPC call to install the selected extension
     *
     * @param $url
     *
     * @throws pm_Exception
     */
    private static function installExtensionApi($url)
    {
        $request = "<server><install-module><url>{$url}</url></install-module></server>";
        $response = pm_ApiRpc::getService('1.6.7.0')->call($request);
        $result = $response->server->{'install-module'}->result;

        if ($result->status != 'ok') {
            throw new pm_Exception($result->errtext);
        }
    }

    /**
     * Checks whether at least one domain is activated in the subscription
     *
     * @return bool
     */
    public static function checkAvailableDomains()
    {
        $xml_data = new SimpleXMLElement('<packet><server><get><stat/></get></server></packet>');
        $xml = \pm_ApiRpc::getService()->call($xml_data->children()[0]->asXml());

        if ($xml->xpath('//objects')[0]->domains == 0) {
            return false;
        }

        return true;
    }

    /**
     * Returns the next step depending on the OS
     *
     * @return int|null|string
     */
    public static function getNextStep()
    {
        $step_next = pm_Settings::get('welcome-step', 1) + 1;
        $white_list_os = self::stepListOs();

        if (!array_key_exists($step_next, $white_list_os)) {
            foreach ($white_list_os as $key => $value) {
                if ($key >= $step_next) {
                    return $key;
                }
            }

            end($white_list_os);

            return key($white_list_os);
        }

        return $step_next;
    }

    /**
     * Creates a white list of allowed steps depending on the OS
     *
     * @return array
     */
    public static function stepListOs()
    {
        if (pm_ProductInfo::isWindows()) {
            return array(
                '1' => 'wp-toolkit',
                '3' => 'pagespeed-insights',
                '4' => 'restart'
            );
        }

        return array(
            '1' => 'wp-toolkit',
            '2' => 'security-advisor',
            '3' => 'pagespeed-insights',
            '4' => 'restart'
        );
    }

    /**
     * Workaround to avoid duplicate message instances when changing view - load only once each 3 seconds
     *
     * @return bool
     */
    public static function addMessage()
    {
        $executed = (int) pm_Settings::get('executed', 0);

        if (empty($executed)) {
            return true;
        }

        if (time() - $executed >= 3) {
            return true;
        }

        return false;
    }
}
