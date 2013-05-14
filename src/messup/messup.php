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
 */
include_once('config.php');

// We need sessions to identify the user
$gl['version'] = "0.1";
session_start();
$mc = new messupChat($gl);
$mc->handleRequest();

class messupChat {
	private $_sid = null;
	private $_gl;

	function __construct($gl) {
		$this->_sid = session_id();
		$this->_gl = $gl;
		if (!$this->checkConfiguration()) {
			// TODO: return error message: configuration not complete
		}
	}

	public function handleRequest() {
		if ($_GET['type'] == 'ajax') {
			if ($_SESSION['isAgent'] == 1) {
				touch($this->_gl['chatfilepath'].'agentonline');
			}
			switch($_POST['form']) {
				case 'checkmsg':
					$this->showConversation($_POST['latestMessage']);
					break;
				case 'endchat':
					$this->endConversation();
					break;
				case 'newchat':
					$this->startConversation();
					break;
				case 'sendmsg':
					$this->sendMessage();
					break;
				case 'agentlogin':
					$this->loginAgent();
					break;
				case 'newchats':
					if ($_SESSION['isAgent'] == 1) {
						$this->returnChatList();
					} else {
						$this->requestLogIn();
					}
					break;
				case 'assignagent':
					if ($_SESSION['isAgent'] == 1) {
						$this->assignChat($_POST['convid'], $_SESSION['agent']['id']);
					} else {
						$this->requestLogIn();
					}
					break;

			}
			$ans['err'] = $this->_err;

		}
	}

	private function checkConfiguration() {
		// TODO: check for direct write access to conversation file folder
		if (!is_dir($this->_gl['chatfilepath'])) {
			mkdir ($this->_gl['chatfilepath']);
		}
		// TODO: check if http read access is blocked for conversation file folder
		// TODO: check if at least 1 agent is available
	}

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
		if ($ok === true) {
			$result = "OK";
		} else {
			$result = "FALSE";
		}
		$res = array('form' => $_POST['form'], 'res' => $result, 'account' => $_POST['account']);
		if ($ok === true) {
			$res['agent'] = $_SESSION['agent'];
		}
		echo json_encode($res);
	}

	private function startConversation() {

		// TODO: check if conversation file exists already!!!
		if (strlen($this->_sid) > 3 && is_file($this->_gl['chatfilepath'].'conv-'.$this->_sid)) {
			$this->sendMessage();
			//$this->showConversation(0);
		} else {
			// create conversation file, including email address and name
			// check email address and name
			// store data in session

			$_SESSION['messup']['name'] = $_POST['name'];
			$_SESSION['messup']['email'] = $_POST['email'];

			$line = date('d.m.Y H:i:s').';'.getenv('REMOTE_ADDR').';'.$_POST['name'].';'.$_POST['email']."\n";
			try {
				$handle = fopen($this->_gl['chatfilepath'].'conv-'.$this->_sid, 'w');
				fwrite($handle, $this->_gl['version']."\n");
				fwrite($handle, $line);
				fclose($handle);
				// the session should know that this user has started a chat.
				$_SESSION['hadchat'] = 1;
				$this->sendMessage();
			} catch (Exception $e) {
				$err[] = 'Problem saving file.';
			}
			// print json ps
			/*$ans['ps'] = $_POST;*/
			if (count($err) > 0) {
				$ans['err'] = $err;
			}
			//echo json_encode($ans);
		}
	}

	private function sendMessage() {
		// do not store the id. count line numbers, instead.
		// TODO: check name + msg for valid content
		if ($_SESSION['isAgent'] == 1) {
			$name = $_SESSION['agent']['name'];
			$convfile = $this->_gl['chatfilepath'].'conv-'.$_POST['convid'];
			$who = 'a';
		} else {
			$name = $_SESSION['messup']['name'];
			$convfile = $this->_gl['chatfilepath'].'conv-'.$this->_sid;
			$who = 'c';
		}

		// TODO: check if file exists already. otherwise, do NOT proceed!
		// return error message!
		if (!is_file($convfile) && $_SESSION['hadchat'] == 1) {
			$errr['field'] = 'session';
			$errr['note'] = 'The chat session does not exist. It might have been cancelled. Please create a new session.';
			$_SESSION['hadchat'] = 0;
			echo json_encode(array('err' => array($errr)));
		} else {


			$msg = str_replace("\r", "", str_replace("\n", "", nl2br(strip_tags(str_replace(';', ',', $_POST['message'])))));
			// line consists of line number + message
			$line = $who.';'.getenv("REMOTE_ADDR").';'.date('d.m.Y H:i:s').';'.$name.';'.$msg."\n";
			// append line to conversation file
			$handle = fopen($convfile, 'a');
			fwrite($handle, $line);
			fclose($handle);

			// print json: all new lines
			$handle = fopen($convfile, 'r');
			$this->showConversation($_POST['latestMessage']);
		}
	}

	private function showConversation($startPos = 0) {
		// read and display conversation file
		$agentonline = $this->checkForOnlineAgent();
		if ($_SESSION['isAgent'] == 1) {
			$filename = $this->_gl['chatfilepath'].'conv-'.$_POST['convid'];
		} else {
			$filename = $this->_gl['chatfilepath'].'conv-'.$this->_sid;
		}
		if (is_file($filename)) {
			$handle = fopen($filename, 'r');
			// version number:
			$row = fgets($handle);
			// chat data:
			$row = fgets($handle);
			// read messages:
			$i = 0;
			while (!feof($handle)) {
				$i += 1;
				$row = fgets($handle);
				if ($i > $startPos && strlen($row) > 3) {
					$parts = explode(';', $row);
					$line['id'] = $i;
					$line['who'] = $parts[0];
					$line['time'] = $parts[2];
					$line['name'] = $parts[3];
					$line['message'] = $parts[4];
					$lines[] = $line;
				}
			}
			echo json_encode(array('messages' => $lines, 'form' => $_POST['form'], 'convid' => $_POST['convid'], 'online' => $agentonline));
		} elseif ($_SESSION['hadchat'] == 1) {
			$errr['field'] = 'session';
			$errr['note'] = 'The chat session does not exist. It might have been cancelled. Please create a new session.';
			$_SESSION['hadchat'] = 0;
			echo json_encode(array('err' => array($errr), 'online' => $agentonline));
		} else {
			echo json_encode(array('online' => $agentonline));
		}
		// startpos = line number which is the last line received by the client
		//	>> so send all lines from that one
	}

	private function endConversation() {
		// send email to both conversation participants
		if ($_SESSION['isAgent']) {
			$filename = $this->_gl['chatfilepath'].'conv-'.$_POST['convid'];
		} else {
			$filename = $this->_gl['chatfilepath'].'conv-'.$this->_sid;
			$_SESSION['hadchat'] = 0;
		}
		if (is_file($filename)) {
			$handle = fopen($filename, 'r');
			// version number:
			$version = fgets($handle);
			// chat data:
			$chatdata = explode(';', fgets($handle));
			$mtxt = 'Dear '.$chatdata[2].",\n\nthank you for contacting us. Please find the chat protocol attached to this email. Please be aware of the fact that this protocol is for your reference only, and not legally binding in any way. All information is subject to change. (Your chat from ".$chatdata[0].", email: ".trim($chatdata[3])."):\n\n";
			// read messages:
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
			unlink($filename);
			echo json_encode(array('ps' => $_POST, 'form' => $_POST['form']));
		}
		$mtxt = '';

		// delete conversation file
	}

	private function returnChatList($onlyUnAssigned = true) {
		$assigned = Array();
		$files = Array();
		// list all open chats, for agents
		if ($handle = opendir($this->_gl['chatfilepath'])) {
			while (false !== ($file = readdir($handle))) {
				if (is_file($this->_gl['chatfilepath'].$file)) {
					$files[] = $file;
					// filter all assigned chats
					$parts = preg_split( "/(\-|\.)/", $file );
					if ($parts[0] == 'assign') {
						$assigned[$parts[0]] = 1;
					}
				}
			}
		} else {
			$err = 'Cannot open dir '.$this->_gl['chatfilepath'];
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
		$fname = $this->_gl['chatfilepath'].'-'.session_id().'-'.$_SESSION['agent']['id'];
		$handle = fopen($fname, 'a');
		fclose($handle);
		// every agent can push any chat
	}

	private function exportConfiguration() {
		// writes configuration to config file
		// not secure, but this creates opportunities for updates
		// without loosing configuration
	}

	private function importConfiguration() {
		// reads configuration from config file
		// not secure, but this creates opportunities to update the code
		// without loosing configuration
	}

	private function loginFailureMail() {
		// sends mail for wrong login attempt
	}

	private function lockUser() {
		// locks user (?)
	}

	private function checkForOnlineAgent() {
		$on = false;
		// checks for the last ping from an online agent to determine if
		// there's anybody reachable
		$lastagentfile = $this->_gl['chatfilepath'].'agentonline';
		if (is_file($lastagentfile)) {
			$t = filemtime($lastagentfile);
			if ($t < time() - 10*60) {
				unlink($lastagentfile);
			} else {
				$on = true;
			}
		}
		return $on;
	}

	private function returnChatData($chatId) {
		$filename = $this->_gl['chatfilepath'].'conv-'.$chatId;
		$res = Array();
		if (is_file($filename)) {
			$handle = fopen($filename, 'r');
			// version number:
			$version = fgets($handle);
			// chat data:
			$chatdata = explode(';', fgets($handle));
			fclose($handle);
			$res['starttime'] = $chatdata[0];
			$res['ip'] = $chatdata[1];
			$res['name'] = $chatdata[2];
			$res['email'] = trim($chatdata[3]);
		}
		return $res;
	}

	private function requestLogIn() {
		echo json_encode(array('err' => '403'));
	}
}