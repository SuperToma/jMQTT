<?php
if (! isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

sendVarToJS('eqType', 'jMQTT');
include_file('desktop', 'jMQTT.globals', 'js', 'jMQTT');
include_file('desktop', 'jMQTT.functions', 'js', 'jMQTT');

// Send daemon current state
sendVarToJS('jmqtt_globals.daemonState', jMQTTDaemon::state());

/** @var jMQTT[][] $eqNonBrokers */
$eqNonBrokers = jMQTT::getNonBrokers();
/** @var jMQTT[] $eqBrokers */
$eqBrokers = jMQTT::getBrokers();

$eqBrokersName = array();
foreach ($eqBrokers as $id => $eqL) {
    $eqBrokersName[$id] = $eqL->getName();
}
sendVarToJS('jmqtt_globals.eqBrokers', $eqBrokersName);

// TODO: Handle CSS in its own style file
//  labels: quality, css
?>
<style>
td.fitwidth { white-space: nowrap; }
div.eqLogicThumbnailContainer.containerAsTable i.fa-sign-in-alt.fa-rotate-90 { margin-bottom: 0px; }
span.hiddenAsTable i.fas.fa-sign-in-alt { font-size:0.9em !important;position:absolute;margin-top:67px;margin-left:3px; }
span.hiddenAsTable i.far.fa-square { font-size:0.9em !important;position:absolute;margin-top:67px;margin-left:5px; }
span.hiddenAsTable i.fas.status-circle { font-size:1em !important;  position:absolute;margin-top:23px;margin-left:55px; }
span.hiddenAsTable i.fas.eyed { font-size:0.9em !important;position:absolute;margin-top:25px;margin-left:4px; }
span.hiddenAsCard i.fas.fa-sign-in-alt { margin-right:10px;vertical-align:top;margin-top:-3px;margin-left:-5px!important; }
span.hiddenAsCard i.fas.status-circle { margin-right:6px; }
textarea.form-control.input-sm.modifiedVal { color: darkorange!important; font-weight: bold!important; }
input:not(.numInput):not(.btn):not(.dial):not([type=radio]):not([type=image]):not(.expressionAttr):not(.knob):not([type=checkbox]).topicMismatch,
textarea.form-control.input-sm.topicMismatch { background: rgba(248, 216, 0, 0.25)!important; font-weight: bold!important; }
div.eqLogicDisplayCard[jmqtt_type="broker"] { background: rgba(248, 216, 0, 0.25)!important; }
div.dropzone { z-index:6;width:100%;max-width:98%;height:100%;position:absolute;background-color:lightgray;display:flex;justify-content:center;align-items:center;border:2px dashed green;font-weight:bold; }
textarea.eqLogicAttr.form-control.blured { filter: blur(4px); }
textarea.eqLogicAttr.form-control.blured:hover { filter: none; }
textarea.eqLogicAttr.form-control.blured:focus { filter: none; }
i.fas.fa-minus-circle.cmdAction { margin-top: 5px; }
textarea.eqLogicAttr.form-control.cert { font-family: "CamingoCode",monospace; font-size:small!important; line-height:normal; height:62px; }
.w30 { width: 30px; }
.w18 { width: 18px; text-align: center; font-size: 0.9em; }
</style>
<?php

/**
 *
 * @param jMQTT $eqL
 */
function displayEqLogicCard($eqL) {
    echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqL->getId() . '" jmqtt_type="' . $eqL->getType() . '">';
    echo '<span class="hiddenAsTable"></span>';
    if ($eqL->getType() == 'brk')
        echo '<img class="lazy" src="plugins/jMQTT/core/img/node_broker.svg" />';
    else
        echo '<img class="lazy" src="plugins/jMQTT/core/img/node_.svg" />';
    echo '<span class="name">' . $eqL->getHumanName(true, true) . '</span>';
    echo '<span class="hiddenAsCard input-group displayTableRight hidden"></span></div>'."\n";
}

/**
 *
 * @param string $action_name action name of the card
 * @param string $fa_icon icon class name
 * @param string $action data-action value (default: '')
 * @param string $class div class (default: 'logoSecondary')
 */
function displayActionCard($action_name, $fa_icon, $action = '', $class = 'logoSecondary') {
    echo '<div class="eqLogicAction cursor ' . $class . '" data-action="' . $action . '">';
    echo '<i class="fas ' . $fa_icon . '"></i><br/><span>' . $action_name . '</span></div>'."\n";
}
?>
<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <div class="row">
            <div class="col-sm-10">
                <legend><i class="fas fa-cog"></i>&nbsp;{{Gestion}}</legend>
                <div class="eqLogicThumbnailContainer">
                <?php
                displayActionCard('{{Configuration}}', 'fa-wrench', 'gotoPluginConf');
                displayActionCard('{{Ajouter un broker}}', 'fa-server', 'addJmqttBrk');
                displayActionCard('{{Santé}}', 'fa-medkit', 'healthMQTT');
                if (isset($_GET['debug']) || config::byKey('debugMode', 'jMQTT', "0") === "1" /* || log::getLogLevel('jMQTT') <= 100 */)
                    displayActionCard('{{Debug}}', 'fa-bug', 'debugJMQTT');
                displayActionCard('{{Templates}}', 'fa-cubes', 'templatesMQTT');
                displayActionCard('{{Ajouter}}', 'fa-plus-circle', 'addJmqttEq'/*, 'logoPrimary'*/);
                ?>
                </div>
            </div>
            <?php
            // TODO: Handle core4.3 `createCommunityPost` compatibility
            //  Remove when Jeedom 4.3 is no longer supported
            //  labels: enhancement, core4.3, php

            // Community card, only displayed if Jeedom version 4.4+
            if (version_compare((jeedom::version() ?? '4.3.0'), '4.4.0', '>=')) {
            ?>
            <div class="col-sm-2">
                <legend><i class="fas fa-comments"></i>&nbsp;Community</legend>
                <div class="eqLogicThumbnailContainer">
                <?php
                 displayActionCard('{{Nouveau post}}', 'fa-ambulance', 'createCommunityPost');
                ?>
                </div>
            </div>
            <?php
            }
            ?>
        </div>
        <div class="input-group" style="margin:5px;">
            <input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">
            <div class="input-group-btn">
                <a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>
                <a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>
            </div>
        </div>
        <?php
        // Check there are orphans first
        $has_orphans = false;
        foreach ($eqNonBrokers as $id => $nonBrokers) {
            if (!isset($eqBrokers[$id])) {
                if (!$has_orphans) {
                    echo '<div class="eqLogicThumbnailContainer">';
                    echo '<legend class="danger"><i class="fas fa-table"></i> {{Mes Equipements orphelins}}&nbsp;<sup>';
                    echo '<i class="fas fa-exclamation-triangle tooltips" title="';
                    echo '{{Ces équipements ne sont associés à aucun broker et ne peuvent donc pas communiquer.}}<br/>';
                    echo '{{Il ne devrait pas y avoir un seul orphelin : supprimez-les ou rattachez-les à un broker.}}"></i></sup></legend>';
                    $has_orphans = true;
                }
                foreach ($nonBrokers as $eqL) {
                    displayEqLogicCard($eqL);
                }
            }
        }
        if ($has_orphans)
            echo '</div>';

        foreach ($eqBrokers as $eqB) {
            echo '<legend><i class="fas fa-table"></i> {{Mes Equipements sur le broker}} <b>' . $eqB->getName() . '</b> (' . @count($eqNonBrokers[$eqB->getId()]) . ')</legend>';
            echo '<div class="eqLogicThumbnailContainer">';
            displayEqLogicCard($eqB);
            if (isset($eqNonBrokers[$eqB->getId()])) {
                foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
                    displayEqLogicCard($eqL);
                }
            }
            echo '</div>';
        }
        ?>
    </div>

    <div class="col-xs-12 eqLogic" data-eqLogic_type="jMQTT" style="display: none;">
        <div class="row">
            <div class="input-group pull-right" style="display:inline-flex">
                <a class="btn btn-primary btn-sm eqLogicAction typ-std roundedLeft toDisable tooltips" data-action="createTemplate" style="display: none;" title="{{Créer Template}}"><i class="fas fa-cubes"></i></a>
                <a class="btn btn-warning btn-sm eqLogicAction typ-std toDisable tooltips" data-action="applyTemplate" style="display: none;" title="{{Appliquer Template}}"><i class="fas fa-share"></i></a>
                <a class="btn btn-success btn-sm eqLogicAction typ-std toDisable tooltips" data-action="updateTopics" style="display: none;" title="{{Modifier Topics}}"><i class="fas fa-pen"></i></a>
                <a class="btn btn-primary btn-sm eqLogicAction typ-std tooltips" data-action="jsonPathTester" style="display: none;" title="{{Testeur Chemin JSON}}"><i class="fas fa-check"></i></a>
                <a class="btn btn-default btn-sm eqLogicAction typ-std toDisable tooltips" data-action="copy" style="display: none;" title="{{Dupliquer}}"><i class="fas fa-copy"></i></a>
                <a class="btn btn-default btn-sm eqLogicAction tooltips" data-action="configure" title="{{Configuration avancée}}"><i class="fas fa-cogs"></i></a>
                <a class="btn btn-success btn-sm eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
                <a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>&nbsp;
            </div>
            <div class="input-group pull-left" style="display:inline-flex">
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
                    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="eqlogictab" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
                    <li role="presentation" class="typ-brk" style="display: none;"><a href="#brokertab" aria-controls="brokertab" role="tab" data-toggle="tab"><i class="fas fa-rss"></i> {{Broker}}</a></li>
                    <li role="presentation" class="typ-std" style="display: none;"><a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
                    <li role="presentation" class="typ-std typ-brk" style="display: none;"><a href="#realtimetab" aria-controls="realtimetab" role="tab" data-toggle="tab" style="padding: 10px 14px;"><i class="fas fa-align-left"></i><span class="typ-brk" style="display: none;"> {{Temps Réel}}</span></a></li>
                    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="refreshPage"><i class="fas fa-sync"></i></a></li>
                </ul>
            </div>
        </div>
        <div id="menu-bar" style="display: none;">
            <div class="form-actions">
                <a class="btn btn-info btn-xs eqLogicAction toDisable" data-action="addMQTTAction"><i class="fas fa-plus-circle"></i> {{Ajouter une commande action}}</a>
                <a class="btn btn-warning btn-xs eqLogicAction toDisable" data-action="addMQTTInfo"><i class="fas fa-plus-circle"></i> {{Ajouter une commande info}}</a>
                <div class="btn-group pull-right" data-toggle="buttons">
                    <a class="btn btn-primary btn-xs roundedLeft eqLogicAction active" data-action="classicView"><input type="radio" checked><i class="fas fa-list-alt"></i> Classic </a>
                    <a class="btn btn-default btn-xs roundedRight eqLogicAction" data-action="jsonView"><input type="radio"><i class="fas fa-sitemap"></i> JSON </a>
                </div>
            </div>
            <hr style="margin-top: 5px; margin-bottom: 5px;">
        </div>
        <div class="tab-content" style="height:calc(100vh - 140px)!important;overflow:auto;overflow-x:hidden;">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <?php include_file('desktop', 'jMQTT_eqpt', 'php', 'jMQTT'); ?>
            </div>
            <div role="tabpanel" class="tab-pane toDisable" id="brokertab">
                <?php include_file('desktop', 'jMQTT_broker', 'php', 'jMQTT'); ?>
            </div>
            <div role="tabpanel" class="tab-pane toDisable" id="realtimetab">
                <table id="table_realtime" class="table tree table-bordered table-condensed table-striped">
                    <thead style="position:sticky;top:0;z-index:5;">
                        <tr>
                            <td colspan="5" data-sorter="false" data-filter="false">
                                <div class="col-lg-3">
                                    <label class="col-lg-4 control-label" style="text-align:right;">{{Souscriptions}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                        title="{{Topics de souscription utilisés lorsque le mode Temps Réel est actif sur ce Broker.<br/>Plusieurs topics peuvent être fournis en les séparant par des '|' (pipe).<br/>Par défaut, le topic de souscription est '#', donc tous les topics, ce qui peut être beaucoup sur certaines installations.}}"></i></sup></label>
                                    <input class="col-lg-8 form-control" id="mqttIncTopic">
                                </div>
                                <div class="col-lg-3">
                                    <label class="col-lg-4 control-label" style="text-align:right;">{{Exclusions}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                        title="{{Topics à ne pas remonter lorsque le mode Temps Réel est actif.<br/>Plusieurs topics peuvent être fournis en les séparant par des '|' (pipe).<br/>Par exemple, le topic d'auto-découverte HA ('homeassistant/#') est souvent exclu, car il est très verbeux.}}"></i></sup></label>
                                    <input class="col-lg-8 form-control" id="mqttExcTopic">
                                </div>
                                <div class="col-lg-2">
                                    <label class="col-lg-8 control-label" style="text-align:right;">{{Retained}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                        title="{{Recevoir aussi les payload qui sont déjà retenus par le Broker.}}"></i></sup></label>
                                    <input type="checkbox" class="form-control" id="mqttRetTopic" checked="false">
                                </div>
                                <div class="col-lg-2">
                                    <label class="col-lg-6 control-label" style="text-align:right;">{{Durée}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                        title="{{Le temps que le mode Temps Réel doit fonctionner en secondes [1-3600].}}"></i></sup></label>
                                    <input class="col-lg-6 form-control" id="mqttDuration">
                                </div>
                                <div class="col-lg-2">
                                    <div class="input-group pull-right">
                                        <a class="btn btn-default btn-sm eqLogicAction roundedLeft" data-action="startRealTimeMode"><i class="fas fa-sign-in-alt fa-rotate-90"></i> {{Lancer}}</a>
                                        <a class="btn btn-danger  btn-sm eqLogicAction roundedLeft" data-action="stopRealTimeMode" style="display: none;"><i class="fas fa-square"></i> {{Arrêter}}</a>
                                        <a class="btn btn-success btn-sm eqLogicAction" data-action="playRealTime" style="display: none;"><i class="fa fa-play"></i> {{Reprendre}}</a>
                                        <a class="btn btn-warning btn-sm eqLogicAction" data-action="pauseRealTime" style="display: none;"><i class="fa fa-pause"></i> {{Pause}}</a>
                                        <a class="btn btn-warning btn-sm eqLogicAction roundedRight" data-action="emptyRealTime"><i class="fas fa-trash"></i> {{Vider}}</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th style="width:180px;" data-sorter="text">{{Date du message}}</th>
                            <th data-sorter="topics" class="filter-match /*filter-parsed*/">{{Topic}}</th>
                            <th data-sorter="inputs">{{Valeur}}</th>
                            <th style="width:80px;" data-sorter="options" class="filter-select /*filter-parsed*/">{{Options}}</th>
                            <th style="width:130px;" data-sorter="false" data-filter="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <!--
                TODO: Add here the "Discovery" tab
                 labels: enhancement, html
            -->
            <div role="tabpanel" class="tab-pane toDisable" id="commandtab">
                <table id="table_cmd" class="table tree table-bordered table-condensed table-striped">
                    <thead>
                        <tr>
                            <th style="width:1px;">#</th>
                            <th style="min-width:150px;width:300px;">{{Nom}}</th>
                            <th style="width:130px;">{{Type}}</th>
                            <th style="min-width:180px;">{{Topic}}</th>
                            <th style="min-width:180px;">{{Valeur}}</th>
                            <th style="min-width:100px;width:120px;">{{Paramètres}}</th>
                            <th style="min-width:100px;width:120px;">{{Options}}</th>
                            <th style="min-width:115px;width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_file('desktop', 'jMQTT', 'js', 'jMQTT'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
