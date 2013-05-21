<?php
/**
 * Integrates support/sales chat functionality into any website.
 * Line5 e.K.
 * http://www.line5.eu
 * 
 * source code + license:
 * https://github.com/Line5/messup
 *
 * Chat data is stored in Sessions. No database needed.
 * All the server-side code is within this file. It might be 
 * easier for programmers to use different files, but for 
 * the admin who needs to install the whole thing, it might be 
 * easier to just use this, one file.
 * 
 * More information: http://code.google.com/p/messupclientwin
 * Twitter: https://twitter.com/MessupChat
 */
include_once('config.php');

// Version of the conversation file format
$gl['fileformatversion'] = "0.1";

// We need sessions to identify the user
session_start();

// start the program
$mc = new messupChat($gl);
$mc->handleRequest();

class messupChat {
	private $_sid = null;
	private $_gl;

	/**
	 * Constructor
	 * @param Array $gl - this is an array of program settings
	 */
	function __construct($gl) {
		$this->_sid = session_id();
		$this->_gl = $gl;
		if (!$this->checkConfiguration()) {
			// TODO: return error message: configuration not complete
		}
	}

	public function handleRequest() {
		// Currently, there are only requests of type "ajax". However, we better check it.
		if ($_GET['type'] == 'ajax') {
			if ($_SESSION['isAgent'] == 1) {
				/* The <agentonline> file exists only if an agent
				 * is online. Hence if the current request is caused by
				 * an agent, we need to "touch" that file (= update its timestamp).
				 */				
				touch($this->_gl['convfilepath'].'agentonline');
			}
			/*
			 * The *form* field of the post request tells us the requested action.
			 * Currently, each request needs to be a "post" request.   
			 */
			switch($_POST['form']) {
				case 'checkmsg':
					// check for new messages and return them
					$this->showConversation($_POST['latestMessage']);
					break;
				case 'endchat':
					// end a conversation (= delete file on server)
					$this->endConversation();
					break;
				case 'newchat':
					// create a conversation
					$this->startConversation();
					break;
				case 'sendmsg':
					// send a message
					$this->sendMessage();
					break;
				case 'agentlogin':
					// try to login an agent
					$this->loginAgent();
					break;
				case 'newchats':
					// agents only: returns a list of all chats
					if ($_SESSION['isAgent'] == 1) {
						$this->returnChatList();
					} else {
						$this->requestLogIn();
					}
					break;
				case 'assignagent':
					// not implemented yet ... should assign an agent to a chat.
					if ($_SESSION['isAgent'] == 1) {
						$this->assignChat($_POST['convid'], $_SESSION['agent']['id']);
					} else {
						$this->requestLogIn();
					}
					break;

			}
		}
	}
	/**
	 * Checks if the installation and configuration of the software are 
	 * alright.
	 */
	private function checkConfiguration() {
		// TODO: check for direct write access to conversation file folder

		/**
		 * Create the conversation directory, if it does not exist.
		 * The software stores all conversations there, hence it should 
		 * not be publicly readable.
		 */
		if (!is_dir($this->_gl['convfilepath'])) {
			mkdir ($this->_gl['convfilepath']);
		}
		// TODO: check if http read access is blocked for conversation file folder
		// TODO: check if at least 1 agent is available
	}

	/**
	 * Handles the login of an agent.
	 * Usernames and passwords are stored within config.php,
	 * The login status is stored in the session ($_SESSION['isAgent']).
	 * 
	 * The function returns the result of the login process directly 
	 * to the browser / client, in JSON format. The return value 
	 * is either "OK" or "FALSE".
	 * 
	 */
	private function loginAgent() {
		$ok = false;
		// check username and password against agent array
		foreach ($this->_gl['agent'] as $agent) {
			if ($agent['email'] == $_POST['email'] && $agent['pass'] == $_POST['pass'] && $agent['disabled'] == false) {
				$_SESSION['isAgent'] = 1;
				$_SESSION['agent']['email'] = $agent['email'];
				$_SESSION['agent']['name'] = $agent['name'];
				$_SESSION['agent']['id'] = $agent['id'];
				$myAgent = $agent;
				$ok = true;
			}
		}
		
		// compose the result array
		$res = array('form' => $_POST['form'], 'account' => $_POST['account']);
		if ($ok === true) {
			$res['res'] = "OK";
			$res['agent'] = $_SESSION['agent'];
		} else {
			$res['res'] = "FALSE";
		}
		// return json code to browser / client		
		echo json_encode($res);
	}

	/**
	 * Starts a conversation: Creates a conv file, containing 
	 * appropriate and necessary information (name, ip, time, ...)
	 */
	private function startConversation() {
		// If a conv file exists already, just update it with the newly sent message.
		// The "message" is part of the request for a new conversation.
		if (strlen($this->_sid) > 3 && is_file($this->_gl['convfilepath'].'conv-'.$this->_sid)) {
			$this->sendMessage();
		} else {
			// create conversation file, including email address and name
			// TODO: check email address and name
			// TODO: return error message, if anything is wrong

			// store data in session
			$_SESSION['messup']['name'] = $_POST['name'];
			$_SESSION['messup']['email'] = $_POST['email'];

			// compose information line for convfile
			$line = date('d.m.Y H:i:s').';'.getenv('REMOTE_ADDR').';'.$_POST['name'].';'.$_POST['email']."\n";
			
			// write to file.
			try {
				$handle = fopen($this->_gl['convfilepath'].'conv-'.$this->_sid, 'w');
				// first line of the file reflects the file format version
				fwrite($handle, $this->_gl['fileformatversion']."\n");
				// second line of the file consists of the composed conversation header information
				fwrite($handle, $line);
				fclose($handle);
				// the session should know that this user has started a chat.
				$_SESSION['hadchat'] = 1;
				// a message is part of the "new chat" request. The message can be written 
				// as soon as the conversation file has been created. 
				$this->sendMessage();
			} catch (Exception $e) {
				$err[] = 'Problem saving file.';
			}
			if (count($err) > 0) {
				$ans['err'] = $err;
				// TODO: Errors should be returned somehow here...
			}
		}
	}

	/**
	 * Sends a message - either from the agent or from the visitor.
	 * Each message is stored as one line in the conversation file,
	 * containing sender, time, and message text.
	 * 
	 * The function returns either an error message or all new 
	 * messages directly to the browser, using the json format. 
	 */
	private function sendMessage() {
		// TODO: check name + msg for valid content
		
		// determine conversation file name and who's speaking 
		if ($_SESSION['isAgent'] == 1) {
			$name = $_SESSION['agent']['name'];
			// for the agent, the conversation id is determined via the POST variable 
			$convfile = $this->_gl['convfilepath'].'conv-'.$_POST['convid'];
			$who = 'a'; // "a" means "agent"
		} else {
			$name = $_SESSION['messup']['name'];
			// for the customer, the conversation id equals the session id.
			$convfile = $this->_gl['convfilepath'].'conv-'.$this->_sid;
			$who = 'c'; // "c" means "customer"
		}

		// Check if file exists already. Otherwise, do NOT proceed - and 
		// return an error message directly to the client!
		if (!is_file($convfile) && $_SESSION['hadchat'] == 1) {
			$errr['field'] = 'session';
			$errr['note'] = 'The chat session does not exist. It might have been cancelled. Please create a new session.';
			$_SESSION['hadchat'] = 0;
			echo json_encode(array('err' => array($errr)));
		} else {
			// format the message / user input (message) 
			$msg = str_replace("\r", "", str_replace("\n", "", nl2br(strip_tags(str_replace(';', ',', $_POST['message'])))));
			// compose the new conversation file line, containing the new message
			// and some additional information 
			$line = $who.';'.getenv("REMOTE_ADDR").';'.date('d.m.Y H:i:s').';'.$name.';'.$msg."\n";
			// append new line to the conversation file
			$handle = fopen($convfile, 'a');
			fwrite($handle, $line);
			fclose($handle);

			// return all new lines to the client
			$handle = fopen($convfile, 'r');
			$this->showConversation($_POST['latestMessage']);
		}
	}

	/**
	 * Sends all messages from number $lastMsgNo + 1 up to the most recent one 
	 * directly via json to the client.
	 * 
	 * The $lastMsgNo ensures that the client does not miss any message, all 
	 * messages are delivered in the appropriate order, and each message is
	 * delivered only once.
	 * 
	 * The function directly returns an array of messages or an error message 
	 * via JSON to the client software.
	 * If no new messages are available, the $lines object returned to the 
	 * client software is empty. 
	 *   
	 * @param number $lastMsgNo - incremental number of the last message which has been received by the client  
	 */
	private function showConversation($lastMsgNo = 0) {
		// determine the name of the conversation file, depending 
		// on who is calling this function
		if ($_SESSION['isAgent'] == 1) {
			// for agents, the conversation id is determined by the client software.			
			$filename = $this->_gl['convfilepath'].'conv-'.$_POST['convid'];
		} else {
			// for customers, the conversation id equals the session id.
			$filename = $this->_gl['convfilepath'].'conv-'.$this->_sid;
			// determine, if an agent is currently online. 
			$agentonline = $this->checkForOnlineAgent();
		}
		if (is_file($filename)) {
			$handle = fopen($filename, 'r');
			// the first line contains the fileformat version number:
			$row = fgets($handle);
			// the second line contains general chat data:
			$row = fgets($handle);
			// each following line contains one message:
			$i = 0;
			while (!feof($handle)) {
				$i += 1;
				$row = fgets($handle);
				if ($i > $lastMsgNo && strlen($row) > 3) {
					// ; is the delimiter character
					$parts = explode(';', $row);
					// compose an array containing the message properties
					// and add it to the $lines array
					$line['id'] = $i;
					$line['who'] = $parts[0];
					$line['time'] = $parts[2];
					$line['name'] = $parts[3];
					$line['message'] = $parts[4];
					$lines[] = $line;
				}
			}
			// return messages directly to the client software via JSON
			echo json_encode(array('messages' => $lines, 'form' => $_POST['form'], 'convid' => $_POST['convid'], 'online' => $agentonline));
		} elseif ($_SESSION['hadchat'] == 1) {
			/* If the session knows about a recent chat, but the file does not exist,
			 * the agent might have ended the chat.
			 * The client should be informed about that.
			 * Return error message directly to the client software via JSON
			 */
			$errr['field'] = 'session';
			$errr['note'] = 'The chat session does not exist. It might have been cancelled. Please create a new session.';
			$_SESSION['hadchat'] = 0;
			echo json_encode(array('err' => array($errr), 'online' => $agentonline));
		} elseif ($_SESSION['isAgent'] != 1) {
			/* If the session does not know anything about an open chat,
			 * and no conversation file is available,
			 * just return information about online agents directly to the client software
			 * via json
			 */
			echo json_encode(array('online' => $agentonline));
		}
	}

	/**
	 * Ends an existing conversation - usually by someone clicking on the 
	 * "end conversation" button (this can be the customer or the agent).
	 * 
	 * Sends an email containing the entire conversation to the agent.
	 * Deletes the conversation file on the server.
	 * 
	 * Returns information about the fact that a conversation ending has been 
	 * requested directly to the client software via json.
	 */
	private function endConversation() {
		// determine conversation file name, depending on who is sending the request
		if ($_SESSION['isAgent']) {
			// for agents, the conversation id is determined by the client software.
			$filename = $this->_gl['convfilepath'].'conv-'.$_POST['convid'];
		} else {
			// for customers, the conversation id equals the session id.
			$filename = $this->_gl['convfilepath'].'conv-'.$this->_sid;
			// tell the session that there is no active chat anymore.
			$_SESSION['hadchat'] = 0;
		}
		// generate an email out of the conversation file
		if (is_file($filename)) {
			$handle = fopen($filename, 'r');
			// the first line of the file consists fo the fileformat version number:
			$version = fgets($handle);
			// the second line of the file contains some general chat data:
			$chatdata = explode(';', fgets($handle));
			// compose the introductory sentence for the email, containing some 
			// general chat data 
			$mtxt = 'Dear '.$chatdata[2].",\n\nthank you for contacting us. Please find the chat protocol attached to this email. Please be aware of the fact that this protocol is for your reference only, and not legally binding in any way. All information is subject to change. (Your chat from ".$chatdata[0].", email: ".trim($chatdata[3])."):\n\n";
			// read messages and append them to the email text:
			$i = 0;
			while (!feof($handle)) {
				$i += 1;
				$row = fgets($handle);
				if ($i > $startPos && strlen($row) > 3) {
					$parts = explode(';', $row);
					$line['id'] = $i;
					$line['who'] = $parts[0];
					$line['ip'] = $parts[1];
					$line['time'] = $parts[2];
					$line['name'] = $parts[3];
					$line['message'] = trim($parts[4]);
					$lines[] = $line;
					$mtxt .= $line['time'].' - '.$line['name'].': '.$line['message']."\n";
				}
			}
			$header = 'From: '.$this->_gl['mail']['senderAddress'] . "\r\n" .
					'Reply-To: '.$this->_gl['mail']['senderAddress']."\r\n".
					'Content-Type: text/text; charset=UTF-8';
			mail($this->_gl['mail']['senderAddress'], 'Chat Protocol', $mtxt, $header);
			// TODO: filename = sid should be safe!!!
			// a certain risk might be that someone injects a filename as session id
			// which would probably result in that file being deleted...
			unlink($filename);
			// return information about the original request directly to the 
			// client software via json.
			echo json_encode(array('ps' => $_POST, 'form' => $_POST['form']));
		}
	}

	private function returnChatList($onlyUnAssigned = true) {
		$assigned = Array();
		$files = Array();
		// list all open chats, for agents
		if ($handle = opendir($this->_gl['convfilepath'])) {
			while (false !== ($file = readdir($handle))) {
				if (is_file($this->_gl['convfilepath'].$file)) {
					$files[] = $file;
					// filter all assigned chats
					$parts = preg_split( "/(\-|\.)/", $file );
					if ($parts[0] == 'assign') {
						$assigned[$parts[0]] = 1;
					}
				}
			}
		} else {
			$err = 'Cannot open dir '.$this->_gl['convfilepath'];
		}
		closedir($handle);

		foreach ($files as $file) {
			$parts = preg_split( "/(\-|\.)/", $file );
			if ($parts[0] == 'conv' && !isset($assigned[$parts[1]])) {
				//$chatlist[] = array('sid' => $parts[1]);
				$chatdata = $this->returnChatData($parts[1]);
				$chatdata['id'] = $parts[1];
				$chatlist[] = $chatdata;
			}
		}

		// if onlyUnAssigned == false, show only those files which do not have an "agent" id
		// returns chat id, name, and messages sent so far
		$res = array('chats' => $chatlist, 'form' => $_POST['form'], 'account' => $_POST['account']);
		if (isset($err)) {
			$res['err'] = $err;
		}
		echo json_encode($res);
	}


	private function assignChat($chatId, $newAgent) {
		// assigns chat to an agent (> push)
		// write file <assign>-<sid>-<agentid>
		$fname = $this->_gl['convfilepath'].'-'.session_id().'-'.$_SESSION['agent']['id'];
		$handle = fopen($fname, 'a');
		fclose($handle);
		// every agent can push any chat
	}

	private function loginFailureMail() {
		// TODO: Should be implemented for security reasons.
		// sends mail for wrong login attempt
	}

	private function lockUser() {
		// locks user (?)
	}

	/**
	 * checks if any agent is online.
	 * 
	 * @return boolean
	 */
	private function checkForOnlineAgent() {
		$on = false;
		/* 
		 * Whenever an agent requests new messages, the timestamp of the
		 * <agentonline> file is being updated.
		 * We hence need to figure out when this update happened for the last time, 
		 * to check, when the last agent has been online. 
		 */ 
		$lastagentfile = $this->_gl['convfilepath'].'agentonline';
		if (is_file($lastagentfile)) {
			$t = filemtime($lastagentfile);
			if ($t < time() - 10*60) {
				// If the file is older than x minutes,
				// delete it. Non-existing file means,
				// no agents are online.
				unlink($lastagentfile);
			} else {
				$on = true;
			}
		}
		return $on;
	}

	/**
	 * returns chat data - reads it directly from the conversation file 
	 * for that purpose.
	 * 
	 * @param number $chatId - id of the chat (for customers = session id)
	 * @return array:unknown string Ambigous <> - Array containing chat data
	 */
	private function returnChatData($chatId) {
		$filename = $this->_gl['convfilepath'].'conv-'.$chatId;
		$res = Array();
		if (is_file($filename)) {
			$handle = fopen($filename, 'r');
			// the first line just contains the fileformat version number:
			$version = fgets($handle);
			// the second line contains chat data:
			$chatdata = explode(';', fgets($handle));
			fclose($handle);
			$res['starttime'] = $chatdata[0];
			$res['ip'] = $chatdata[1];
			$res['name'] = $chatdata[2];
			$res['email'] = trim($chatdata[3]);
		}
		return $res;
	}

	/**
	 * Returns an error message directy to the client software via json.
	 */
	private function requestLogIn() {
		echo json_encode(array('err' => '403'));
	}
}