<?php
/* Copyright (C) 2008-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2007 Regis Houssin        <regis.houssin@inodbox.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file			htdocs/paybox/lib/paybox.lib.php
 *	\ingroup		paybox
 *  \brief			Library for common paybox functions
 */




/**
 * Create a redirect form to paybox form
 *
 * @param	int   	$PRICE		Price
 * @param   string	$CURRENCY	Currency
 * @param   string	$EMAIL		EMail
 * @param   string	$urlok		Url to go back if payment is OK
 * @param   string	$urlko		Url to go back if payment is KO
 * @param   string	$TAG		Full tag
 * @return  int              	1 if OK, -1 if ERROR
 */
function print_paybox_redirect($PRICE, $CURRENCY, $EMAIL, $urlok, $urlko, $TAG)
{
	global $conf, $langs, $db;

	dol_syslog("Paybox.lib::print_paybox_redirect", LOG_DEBUG);

	// Clean parameters
	$PBX_IDENTIFIANT = "2"; // Identifiant pour v2 test
	if (!empty($conf->global->PAYBOX_PBX_IDENTIFIANT)) $PBX_IDENTIFIANT = $conf->global->PAYBOX_PBX_IDENTIFIANT;
	$IBS_SITE = "1999888"; // Site test
	if (!empty($conf->global->PAYBOX_IBS_SITE)) $IBS_SITE = $conf->global->PAYBOX_IBS_SITE;
	$IBS_RANG = "99"; // Rang test
	if (!empty($conf->global->PAYBOX_IBS_RANG)) $IBS_RANG = $conf->global->PAYBOX_IBS_RANG;
	$IBS_DEVISE = "840"; // Currency (Dollar US by default)
	if ($CURRENCY == 'EUR') $IBS_DEVISE = "978";
	if ($CURRENCY == 'USD') $IBS_DEVISE = "840";
	$urlok.="&devise=".$CURRENCY;

	$URLPAYBOX = "";
	if ($conf->global->PAYBOX_CGI_URL_V1) $URLPAYBOX = $conf->global->PAYBOX_CGI_URL_V1;
	if ($conf->global->PAYBOX_CGI_URL_V2) $URLPAYBOX = $conf->global->PAYBOX_CGI_URL_V2;

	if (empty($IBS_DEVISE))
	{
		dol_print_error('', "Paybox setup param PAYBOX_IBS_DEVISE not defined");
		return -1;
	}
	if (empty($URLPAYBOX))
	{
		dol_print_error('', "Paybox setup param PAYBOX_CGI_URL_V1 and PAYBOX_CGI_URL_V2 undefined");
		return -1;
	}
	if (empty($IBS_SITE))
	{
		dol_print_error('', "Paybox setup param PAYBOX_IBS_SITE not defined");
		return -1;
	}
	if (empty($IBS_RANG))
	{
		dol_print_error('', "Paybox setup param PAYBOX_IBS_RANG not defined");
		return -1;
	}

	$conf->global->PAYBOX_HASH = 'SHA512';
	$ModeCGI = empty($conf->global->PAYBOX_HMAC_KEY)?true:false;

	// Definition des parametres vente produit pour paybox
	$IBS_CMD = $TAG;
	$IBS_TOTAL = $PRICE * 100; // En centimes
	$IBS_MODE = 1; // Mode formulaire
	$IBS_PORTEUR = $EMAIL;
	$IBS_RETOUR = "montant:M;ref:R;auto:A;trans:T;ip:I;type:C"; // Format des parametres du get de validation en reponse (url a definir sous paybox)
	$IBS_TXT = ' '; // Use a space
	$IBS_EFFECTUE = $urlok;
	$IBS_ANNULE = $urlko;
	$IBS_REFUSE = $urlko;
	$IBS_BKGD = "#FFFFFF";
	$IBS_WAIT = "2000";
	$IBS_LANG = "GBR"; // By default GBR=english (FRA, GBR, ESP, ITA et DEU...)
	if (preg_match('/^FR/i', $langs->defaultlang)) $IBS_LANG = "FRA";
	if (preg_match('/^ES/i', $langs->defaultlang)) $IBS_LANG = "ESP";
	if (preg_match('/^IT/i', $langs->defaultlang)) $IBS_LANG = "ITA";
	if (preg_match('/^DE/i', $langs->defaultlang)) $IBS_LANG = "DEU";
	if (preg_match('/^NL/i', $langs->defaultlang)) $IBS_LANG = "NLD";
	if (preg_match('/^SE/i', $langs->defaultlang)) $IBS_LANG = "SWE";
	$IBS_OUTPUT = 'E';
	$PBX_SOURCE = 'HTML';
	$PBX_TYPEPAIEMENT = 'CARTE';
	$PBX_HASH = $conf->global->PAYBOX_HASH;
	//$PBX_TIME = dol_print_date(dol_now(), 'dayhourrfc', 'gmt');
	$PBX_TIME = date('c') //go the easy way for now
	
	// we use an array so we can sort and calculate easily HMAC
	$PBX_ARRAY = array();	
	$PBX_ARRAY['PBX_SITE']=$IBS_SITE; 		
	if ($ModeCGI)$PBX_ARRAY["IBS_MODE"]=$IBS_MODE;	 		
	$PBX_ARRAY["PBX_RUF1"]="POST";	 		
	$PBX_ARRAY["PBX_RANG"]=$IBS_RANG;	 		
	$PBX_ARRAY["PBX_TOTAL"]=$IBS_TOTAL;		
	$PBX_ARRAY["PBX_DEVISE"]=$IBS_DEVISE;		
	$PBX_ARRAY["PBX_CMD"]=$IBS_CMD;	 		
	$PBX_ARRAY["PBX_PORTEUR"]=$IBS_PORTEUR;		
	$PBX_ARRAY["PBX_RETOUR"]=$IBS_RETOUR;		
	$PBX_ARRAY["PBX_EFFECTUE"]=$IBS_EFFECTUE;	
	// $PBX_ARRAY["PBX_REPONDRE_A"]=$IBS_EFFECTUE;	//for when we will manage IPN correctly
	$PBX_ARRAY["PBX_ANNULE"]=$IBS_ANNULE;		
	$PBX_ARRAY["PBX_REFUSE"]=$IBS_REFUSE;	
	if ($ModeCGI)$PBX_ARRAY["PBX_WAIT"]=$IBS_WAIT;		
	if ($ModeCGI)$PBX_ARRAY["PBX_LANG"]=$IBS_LANG;		
	if ($ModeCGI)$PBX_ARRAY["PBX_OUTPUT"]=$IBS_OUTPUT;		
	$PBX_ARRAY["PBX_IDENTIFIANT"]=$PBX_IDENTIFIANT;	
	// $PBX_ARRAY["PBX_SOURCE"]=$PBX_SOURCE;	 	
	// $PBX_ARRAY["PBX_TYPEPAIEMENT"]=$PBX_TYPEPAIEMENT;
	$PBX_ARRAY["PBX_TIME"]=$PBX_TIME;
	
	if(!$ModeCGI){
		$PBX_ARRAY["PBX_HASH"]=$PBX_HASH;
		ksort($PBX_ARRAY); //array alphabetical sorting
		dol_syslog("Soumission Paybox", LOG_DEBUG);
		$params = array();
		foreach($PBX_ARRAY as $var => $val){
			dol_syslog($var.": ".$val, LOG_DEBUG);
			$params[] = $var.'='.$val;
		} 
		$msg = implode('&', $params);
		$binKey = pack("H*", dol_decode($conf->global->PAYBOX_HMAC_KEY));
		$hmac = strtoupper(hash_hmac($PBX_HASH, $msg, $binKey));
		$PBX_ARRAY["PBX_HMAC"]=$hmac;
	}
	header("Content-type: text/html; charset=".$conf->file->character_set_client);
	header("X-Content-Type-Options: nosniff");

	print '<html>'."\n";
	print '<head>'."\n";
	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=".$conf->file->character_set_client."\">\n";
	print '</head>'."\n";
	print '<body>'."\n";
	print "\n";

	// Formulaire pour module Paybox
	print '<form action="'.$URLPAYBOX.'" NAME="Submit" method="POST">'."\n";

	    // Formulaire pour module Paybox
    print '<form action="'.$URLPAYBOX.'" NAME="Submit" method="POST">'."\n";
	foreach($PBX_ARRAY as $var => $val){
		print '<input type="hidden" name="'.$var.'" value="'.$val.'">'."\n";
	} 
	print '</form>'."\n";
 
	print "\n";
	print '<script type="text/javascript" language="javascript">'."\n";
	print '	document.Submit.submit();'."\n";
	print '</script>'."\n";
	print "\n";
	print '</body></html>'."\n";
	print "\n";

	return;
}
