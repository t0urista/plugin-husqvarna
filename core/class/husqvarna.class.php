<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/*
Codes d'erreur : 
0	OK
1	Tondeuse en dehors zone de tonte
2	Pas de signal boucle
4	Problème capteur boucle avant
5	Problème capteur boucle arrière
6	Problème capteur de boucle
7	Problème capteur de boucle
8	Code PIN incorrect
9	Tondeuse coincée
10	Tondeuse à l'envers (sur le dos)
11	Batterie faible
12	Batterie vide
13	Robot bloqué (Erreur de propulsion)
15	Robot soulevé
16	Coincée dans station charge
17	Station de charge inaccessible
18	Problème capteur collision AR
19	Problème capteur collision AV
20	Moteur de roue droite bloqué
21	Moteur de roue gauche bloqué
22	Problème moteur de roue droite
23	Problème moteur de roue gauche
24	Problème moteur de coupe
25	Disque de coupe bloqué
26	Combinaison de sous dispositifs non valide
27	Réglages restaurés
28	Problème du circuit de mémoire
30	Problème de batterie
31	Problème bouton STOP
32	Problème de capteur d’inclinaison
33	Tondeuse inclinée
35	Moteur de roue droite surchargé
36	Moteur de roue gauche surchargé
37	Courant de charge trop élevé
38	Problème de communication entre la carte MMI et la carte électronique principale
42	Plage hauteur de coupe limitée
43	Ajustement hauteur coupe imprévu
44	Problème hauteur de coupe
45	Problème entraînement coupe
46	Plage hauteur de coupe limitée
47	Problème entraînement coupe
69	Arrêt manuel de l'interrupteur
74	En dehors de la zone de protection virtuelle
78	Défaut d’entrainement

*/


/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../3rdparty/husqvarna_api.class.php';

class husqvarna extends eqLogic {
    /*     * *************************Attributs****************************** */

    /*     * ***********************Methode static*************************** */
    public static function postConfig_password() {
        husqvarna::force_detect_movers();
    }

    public static function force_detect_movers() {
        // Initialisation de la connexion
        log::add('husqvarna','info','force_detect_movers');
        if ( config::byKey('account', 'husqvarna') != "" || config::byKey('password', 'husqvarna') != "" )
        {
            $session_husqvarna = new husqvarna_api();
            $session_husqvarna->login(config::byKey('account', 'husqvarna'), config::byKey('password', 'husqvarna'));
            foreach ($session_husqvarna->list_robots() as $id => $data)
            {
                log::add('husqvarna','debug','Find mover : '.$id);
                if ( ! is_object(self::byLogicalId($id, 'husqvarna')) ) {
                    log::add('husqvarna','info','Creation husqvarna : '.$id.' ('.$data->name.')');
                    $eqLogic = new husqvarna();
                    $eqLogic->setLogicalId($id);
                    $eqLogic->setName($data->name);
                    $eqLogic->setEqType_name('husqvarna');
                    $eqLogic->setIsEnable(1);
                    $eqLogic->setIsVisible(1);
                    $eqLogic->save();
                }
            }
        }
    }

    public function postInsert()
    {
        $this->postUpdate();
    }

    private function getListeDefaultCommandes()
    {
        return array(   "batteryPercent" => array('Batterie', 'info', 'numeric', "%", 0, "GENERIC_INFO", 'badge', 'badge', ''),
                        "connected" => array('ConnectÃ©', 'info', 'binary', "", 0, "GENERIC_INFO", 'alert', 'alert', ''),
                        "mowerStatus" => array('Etat robot', 'info', 'string', "", 0, "GENERIC_INFO", 'badge', 'badge', ''),
                        "operatingMode" => array('Mode de fonctionnement', 'info', 'string', "", 0, "GENERIC_INFO", 'badge', 'badge', ''),
                        "lastErrorCode" => array('Code d\'erreur', 'info', 'numeric', "", 0, "GENERIC_INFO", 'badge', 'badge', ''),
                        "lastErrorMessage" => array('Message d\'erreur', 'info', 'string', "", 0, "GENERIC_INFO", 'badge', 'badge', ''),
                        "commande" => array('Commande', 'action', 'select', "", 0, "GENERIC_ACTION", '', '', 'START|'.__('DÃ©marrer',__FILE__).';STOP|'.__('ArrÃªter',__FILE__).';PARK|'.__('Ranger',__FILE__)),
                        "nextStartSource" => array('Prochain dÃ©part', 'info', 'string', "", 0, "GENERIC_INFO", 'badge', 'badge', ''),
                        "nextStartTimestamp" => array('Heure prochain dÃ©part', 'info', 'string', "ut2", 0, "GENERIC_INFO", 'badge', 'badge', ''),
                        "storedTimestamp" => array('Heure dernier rapport', 'info', 'string', "ut1", 0, "GENERIC_INFO", 'badge', 'badge', '')
        );
    }

    public function postUpdate()
    {
        foreach( $this->getListeDefaultCommandes() as $id => $data)
        {
            list($name, $type, $subtype, $unit, $invertBinary, $generic_type, $template_dashboard, $template_mobile, $listValue) = $data;
            $cmd = $this->getCmd(null, $id);
            if ( ! is_object($cmd) ) {
                $cmd = new husqvarnaCmd();
                $cmd->setName($name);
                $cmd->setEqLogic_id($this->getId());
                $cmd->setType($type);
		$cmd->setUnite($unit);
                $cmd->setSubType($subtype);
                $cmd->setLogicalId($id);
                if ( $listValue != "" )
                {
                    $cmd->setConfiguration('listValue', $listValue);
                }
                $cmd->setDisplay('invertBinary',$invertBinary);
                $cmd->setDisplay('generic_type', $generic_type);
                $cmd->setTemplate('dashboard', $template_dashboard);
                $cmd->setTemplate('mobile', $template_mobile);
                $cmd->save();
            }
	    else
            {
            	$cmd->setType($type);
		$cmd->setSubType($subtype);
		$cmd->setUnite($unit);
		$cmd->setDisplay('invertBinary',$invertBinary);
		$cmd->setDisplay('generic_type', $generic_type);
/*		
		$cmd->setTemplate('dashboard', $template_dashboard);
		$cmd->setTemplate('mobile', $template_mobile);
*/
		if ( $listValue != "" )
		{
			$cmd->setConfiguration('listValue', $listValue);
		}
		$cmd->save();
            }
        }
    }

    public function preRemove() {
    }

   public function getLastErrorMessage($errorCode) {

	switch($errorCode) {
		case 0 : return "Tondeuse OK"; break;
		case 1 : return "Tondeuse en dehors zone de tonte"; break;
		case 2 : return "Pas de signal boucle"; break;
		case 4 : return "Problème capteur boucle avant"; break;
		case 5 : return "Problème capteur boucle arrière"; break;
		case 6 : return "Problème capteur de boucle"; break;
		case 7 : return "Problème capteur de boucle"; break;
		case 8 : return "Code PIN incorrect"; break;
		case 9 : return "Tondeuse coincée"; break;
		case 10 : return "Tondeuse à l'envers (sur le dos)"; break;
		case 11 : return "Batterie faible"; break;
		case 12 : return "Batterie vide"; break;
		case 13 : return "Robot bloqué (Erreur de propulsion)"; break;
		case 15 : return "Robot soulevé"; break;
		case 16 : return "Coincée dans station charge"; break;
		case 17 : return "Station de charge inaccessible"; break;
		case 18 : return "Problème capteur collision AR"; break;
		case 19 : return "Problème capteur collision AV"; break;
		case 20 : return "Moteur de roue droite bloqué"; break;
		case 21 : return "Moteur de roue gauche bloqué"; break;
		case 22 : return "Problème moteur de roue droite"; break;
		case 23 : return "Problème moteur de roue gauche"; break;
		case 24 : return "Problème moteur de coupe"; break;
		case 25 : return "Disque de coupe bloqué"; break;
		case 26 : return "Combinaison de sous dispositifs non valide"; break;
		case 27 : return "Réglages restaurés"; break;
		case 28 : return "Problème du circuit de mémoire"; break;
		case 30 : return "Problème de batterie"; break;
		case 31 : return "Problème bouton STOP"; break;
		case 32 : return "Problème de capteur d’inclinaison"; break;
		case 33 : return "Tondeuse inclinée"; break;
		case 35 : return "Moteur de roue droite surchargé"; break;
		case 36 : return "Moteur de roue gauche surchargé"; break;
		case 37 : return "Courant de charge trop élevé"; break;
		case 38 : return "Problème de communication entre la carte MMI et la carte électronique principale"; break;
		case 42 : return "Plage hauteur de coupe limitée"; break;
		case 43 : return "Ajustement hauteur coupe imprévu"; break;
		case 44 : return "Problème hauteur de coupe"; break;
		case 45 : return "Problème entraînement coupe"; break;
		case 46 : return "Plage hauteur de coupe limitée"; break;
		case 47 : return "Problème entraînement coupe"; break;
		case 69 : return "Arrêt manuel de l'interrupteur"; break;
		case 74 : return "En dehors de la zone de protection virtuelle"; break;
		case 78 : return "Défaut d’entrainement"; break;
		default : return "Défaut inconnu";
	}
    }

    public static function pull() {
        if ( config::byKey('account', 'husqvarna') != "" || config::byKey('password', 'husqvarna') != "" )
        {
            log::add('husqvarna','debug','scan movers info');
            foreach (self::byType('husqvarna') as $eqLogic) {
                $eqLogic->scan();
            }
        }
    }

    public function scan() {
	$verbose=0;
        $session_husqvarna = new husqvarna_api();
        $session_husqvarna->login(config::byKey('account', 'husqvarna'), config::byKey('password', 'husqvarna'));
        if ( $this->getIsEnable() ) {
            $status = $session_husqvarna->get_status($this->getLogicalId());
            log::add('husqvarna','info',"Refresh Status ".$this->getLogicalId());
            foreach( $this->getListeDefaultCommandes() as $id => $data)
            {
                list($name, $type, $subtype, $unit, $invertBinary, $generic_type, $template_dashboard, $template_mobile, $listValue) = $data;
                if ( $type != "action" )
                {
                    $cmd = $this->getCmd(null, $id);
                    if (($cmd->execCmd() != $cmd->formatValue($status->{$id})) || ( $verbose == 1))
                    {
                        $cmd->setCollectDate('');
                        if (substr($unit,0,2) != "ut") {
				if ($id != "lastErrorMessage") {
                            		$cmd->event($status->{$id});
                            		log::add('husqvarna','info',"Refresh ".$id." : ".$status->{$id});
			    		if ($id == "lastErrorCode") {
						$lastErrorMessage = self::getLastErrorMessage($status->{$id});
						log::add('husqvarna','info',"Refresh lastErrorMessage : ".$lastErrorMessage);
						$cmd2 = $this->getCmd(null, "lastErrorMessage");
            					if (is_object($cmd2))  {
							$cmd2->event($lastErrorMessage);
							$cmd2->setCollectDate('');
						}
			    		}
				}

			} else {
                            if ( $status->{$id} == 0 )
                            {
                                $cmd->event(__('Inconnue',__FILE__));
                            } else {
				if ($unit == "ut1") {
					$localTimeStamp = date('d M Y H:i', intval(substr($status->{$id},0,10)));
					log::add('husqvarna','info',"Refresh ".$id." : ".$status->{$id}.", localtime : ". $localTimeStamp);
					$cmd->event($localTimeStamp );
				} else if ($unit == "ut2") {
					$offsetTimeStamp = date("Z");
					$localTimeStamp = date('d M Y H:i', intval(substr($status->{$id},0,10)) - $offsetTimeStamp );
					log::add('husqvarna','info',"Refresh ".$id." : ".$status->{$id}.", localtime : ". $localTimeStamp.", offset : ". $offsetTimeStamp);
					$cmd->event($localTimeStamp );
				}
                            }
                        }
                    }
                }
            }
        }
        $session_husqvarna->logOut();
    }
}

class husqvarnaCmd extends cmd 
{
    /*     * *************************Attributs****************************** */
    public function execute($_options = null) {
        if ( $this->getLogicalId() == 'commande' && $_options['select'] != "" )
        {
            log::add('husqvarna','info',"Commande execute ".$this->getLogicalId()." ".$_options['select']);
            $session_husqvarna = new husqvarna_api();
            $session_husqvarna->login(config::byKey('account', 'husqvarna'), config::byKey('password', 'husqvarna'));
            $eqLogic = $this->getEqLogic();

            $order = $session_husqvarna->control($eqLogic->getLogicalId(), $_options['select']);
            log::add('husqvarna','debug',"Commande traitÃ© : Code = ".$order->status);
        }
    }


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*     * **********************Getteur Setteur*************************** */
}
?>
