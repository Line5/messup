<?php

/**
 * Configure the application here:
 */

// Email sender address and name:
$gl['mail']['senderAddress'] = 'noreply@messend.com';
$gl['mail']['senderName'] = 'MESSUP';

// If mail should be sent to some account for reference, add multiple bcc recipients like this:
// $gl['mail']['bcc'][] = 'boss1@messend.com';
// $gl['mail']['bcc'][] = 'boss2@messend.com';
// $gl['mail']['bcc'][] = ...


// where to save the chat session files
$gl['chatfilepath'] = 'tmp/';

// language. possible values: en, de
$gl['language'] = 'en';

// To add agents, just uncomment the following lines and add your agent's data.
// It is essential to use a unique password, as it is stored unencrypted.
// You can add as many agents as you want. In case you have more than 100, I'd recommend
// a business solution... ;-)
/*
 $gl['agent'][] = Array(
 		'name' => 'Max Mustermann' 					// Name of the agent
 		,'email' => 'max.mustermann@messend.com' 	// the agent's email address
 		,'pass' => 'Z4Be§CmPeuR' 					// the agent's password for messup
 		,'disabled' => false 						// to disable the agent, set to true
 );
*/

/**
 * End of configuration. Do not change anything below this line.
*/