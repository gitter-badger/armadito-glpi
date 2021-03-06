<?php

/**
Copyright (C) 2016 Teclib'

This file is part of Armadito Plugin for GLPI.

Armadito Plugin for GLPI is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Armadito Plugin for GLPI is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with Armadito Plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.

**/

include_once("toolbox.class.php");

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class dealing with Armadito AV module state
 **/
class PluginArmaditoStateModule extends CommonDBTM
{
    protected $jobj;
    protected $state_jobj;
    protected $agentid;

    function __construct()
    {
        //
    }

    function init($agent_id, $state_jobj, $jobj)
    {
        $this->agentid    = $agent_id;
        $this->state_jobj = $state_jobj;
        $this->jobj       = $jobj;

        PluginArmaditoToolbox::logIfExtradebug('pluginArmadito-statemodule', 'New PluginArmaditoStateModule object.');
    }

    /**
     * Get name of this type
     *
     * @return text name of this type by language of the user connected
     *
     **/
    static function getTypeName($nb = 0)
    {
        return __('State Module', 'armadito');
    }

    /**
     * Display tab
     *
     * @param CommonGLPI $item
     * @param integer $withtemplate
     *
     * @return varchar name of the tab(s) to display
     */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if ($item->getType() == 'PluginArmaditoStatedetail') {
            return __('Antivirus modules', 'armadito');
        }
        return '';
    }

    /**
     * Display content of tab
     *
     * @param CommonGLPI $item
     * @param integer $tabnum
     * @param interger $withtemplate
     *
     * @return boolean TRUE
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == 'PluginArmaditoStatedetail') {
            $pfStatemodule = new self();
            $pfStatemodule->showForm($item->fields["plugin_armadito_agents_id"]);
        }
        return TRUE;
    }


    /**
     * Display form
     *
     * @param $agent_id integer ID of the agent
     * @param $options array
     *
     * @return bool TRUE if form is ok
     *
     **/
    function showForm($agent_id, $options = array())
    {

        // Protect against injections
        PluginArmaditoToolbox::validateInt($agent_id);

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr>";
        echo "<th >" . __('Module', 'armadito') . "</th>";
        echo "<th >" . __('Version', 'armadito') . "</th>";
        echo "<th >" . __('Update Status', 'armadito') . "</th>";
        echo "<th >" . __('Last update', 'armadito') . "</th>";
        echo "</tr>";

        $av_modules = $this->findModules($agent_id);

        // TODO: protect against html injections of data (XSS & co)
        foreach ($av_modules as $data) {
            echo "<tr class='tab_bg_1'>";
            echo "<td align='center'>" . htmlspecialchars($data["module_name"]) . "</td>";
            echo "<td align='center'>" . htmlspecialchars($data["module_version"]) . "</td>";
            echo "<td align='center'>" . htmlspecialchars($data["module_update_status"]) . "</td>";
            echo "<td align='center'>" . htmlspecialchars($data["module_last_update"]) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    function toJson()
    {
        return '{}';
    }

    function run()
    {

        if ($this->isStateModuleinDB()) {
            $error = $this->updateStateModule();
        } else {
            $error = $this->insertStateModule();
        }
        return $error;
    }


    function findModules($agent_id)
    {
        global $DB;

        $query = "SELECT * FROM `glpi_plugin_armadito_statedetails`
                 WHERE `plugin_armadito_agents_id`='" . $agent_id . "'";

        $data = array();
        if ($result = $DB->query($query)) {
            if ($DB->numrows($result)) {
                while ($line = $DB->fetch_assoc($result)) {
                    $data[$line['id']] = $line;
                }
            }
        }

        return $data;
    }

    /**
     * Check if module state is already in database
     *
     * @return TRUE or FALSE
     **/
    function isStateModuleinDB()
    {
        global $DB;

        $query = "SELECT id FROM `glpi_plugin_armadito_statedetails`
                 WHERE `plugin_armadito_agents_id`=? AND `module_name`=?";

        $stmt = $DB->prepare($query);

        if (!$stmt) {
            throw new Exception(sprintf("State module select preparation failed."));
        }

        if (!$stmt->bind_param('is', $agent_id, $module_name)) {
            $stmt->close();
            throw new Exception(sprintf("State module select bind_param failed. (%d) %s", $stmt->errno, $stmt->error));
        }

        $agent_id    = $this->agentid;
        $module_name = $this->jobj->name;

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception(sprintf("State module select execution failed. (%d) %s", $stmt->errno, $stmt->error));
        }

        if (!$stmt->store_result()) {
            $stmt->close();
            throw new Exception(sprintf("State module select store_result failed. (%d) %s", $stmt->errno, $stmt->error));
        }

        if ($stmt->num_rows() > 0) {
            $stmt->free_result();
            $stmt->close();
            return true;
        }

        $stmt->free_result();
        $stmt->close();
        return false;
    }

    /**
     * Insert module state in database
     *
     * @return PluginArmaditoError obj
     **/
    function insertStateModule()
    {
        $error     = new PluginArmaditoError();
        $dbmanager = new PluginArmaditoDbManager();
        $dbmanager->init();

        $params["plugin_armadito_agents_id"]["type"] = "i";
        $params["module_name"]["type"]               = "s";
        $params["module_version"]["type"]            = "s";
        $params["module_update_status"]["type"]      = "s";
        $params["module_last_update"]["type"]        = "s";
        $params["itemlink"]["type"]                  = "s";

        $query_name = "NewStateModule";
        $dbmanager->addQuery($query_name, "INSERT", "glpi_plugin_armadito_statedetails", $params);

        if (!$dbmanager->prepareQuery($query_name)) {
            return $dbmanager->getLastError();
        }

        if (!$dbmanager->bindQuery($query_name)) {
            return $dbmanager->getLastError();
        }

        $dbmanager->setQueryValue($query_name, "plugin_armadito_agents_id", $this->agentid);
        $dbmanager->setQueryValue($query_name, "module_name", $this->jobj->name);
        $dbmanager->setQueryValue($query_name, "module_version", "unknown");
        $dbmanager->setQueryValue($query_name, "module_update_status", $this->jobj->mod_status);
        $dbmanager->setQueryValue($query_name, "module_last_update", date("Y-m-d H:i:s", $this->jobj->mod_update_timestamp));
        $dbmanager->setQueryValue($query_name, "itemlink", "ShowAll");

        if (!$dbmanager->executeQuery($query_name)) {
            return $dbmanager->getLastError();
        }

        $dbmanager->closeQuery($query_name);
        $error->setMessage(0, 'New StateModule successfully added in database.');
        return $error;
    }

    /**
     * Uptate module state in database
     *
     * @return PluginArmaditoError obj
     **/
    function updateStateModule()
    {
        $error     = new PluginArmaditoError();
        $dbmanager = new PluginArmaditoDbManager();
        $dbmanager->init();

        $params["module_version"]["type"]            = "s";
        $params["module_update_status"]["type"]      = "s";
        $params["module_last_update"]["type"]        = "s";
        $params["plugin_armadito_agents_id"]["type"] = "i";
        $params["module_name"]["type"]               = "s";


        $query_name = "UpdateStateModule";
        $dbmanager->addQuery($query_name, "UPDATE", "glpi_plugin_armadito_statedetails", $params, array(
            "plugin_armadito_agents_id",
            "module_name"
        ));

        if (!$dbmanager->prepareQuery($query_name)) {
            return $dbmanager->getLastError();
        }

        if (!$dbmanager->bindQuery($query_name)) {
            return $dbmanager->getLastError();
        }

        $dbmanager->setQueryValue($query_name, "plugin_armadito_agents_id", $this->agentid);
        $dbmanager->setQueryValue($query_name, "module_name", $this->jobj->name);
        $dbmanager->setQueryValue($query_name, "module_version", "unknown");
        $dbmanager->setQueryValue($query_name, "module_update_status", $this->jobj->mod_status);
        $dbmanager->setQueryValue($query_name, "module_last_update", date("Y-m-d H:i:s", $this->jobj->mod_update_timestamp));

        if (!$dbmanager->executeQuery($query_name)) {
            return $dbmanager->getLastError();
        }

        $dbmanager->closeQuery($query_name);
        $error->setMessage(0, 'Antivirus successfully updated in database.');
        return $error;

    }
}
?>
