<?php
function pushover($_action, $_data = null) {
	global $pdo;
	global $lang;
  switch ($_action) {
    case 'edit':
      if (!isset($_SESSION['acl']['pushover']) || $_SESSION['acl']['pushover'] != "1" ) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      if (!is_array($_data['username'])) {
        $usernames = array();
        $usernames[] = $_data['username'];
      }
      else {
        $usernames = $_data['username'];
      }
      foreach ($usernames as $username) {
        if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data),
            'msg' => 'access_denied'
          );
          continue;
        }
        $delete = $_data['delete'];
        if ($delete == "true") {
          $stmt = $pdo->prepare("DELETE FROM `pushover` WHERE `username` = :username");
          $stmt->execute(array(
            ':username' => $username
          ));
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_data, $_data),
            'msg' => 'pushover_settings_edited'
          );
          continue;
        }
        $key = $_data['key'];
        $token = $_data['token'];
        $evaluate_x_prio = $_data['evaluate_x_prio'];
        $only_x_prio = $_data['only_x_prio'];
        $senders = array_map('trim', preg_split( "/( |,|;|\n)/", $_data['senders']));
        foreach ($senders as $i => &$sender) {
          if (empty($sender)) {
            continue;
          }
          if (!filter_var($sender, FILTER_VALIDATE_EMAIL) === true) {
            unset($senders[$i]);
            continue;
          }
        }
        $senders = array_filter($senders);
        if (empty($senders)) { $senders = ''; }
        $senders = implode(",", $senders);
        if (!ctype_alnum($key) || strlen($key) != 30) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data, $_data),
            'msg' => 'pushover_key'
          );
          continue;
        }
        if (!ctype_alnum($token) || strlen($token) != 30) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data, $_data),
            'msg' => 'pushover_token'
          );
          continue;
        }
        $title = $_data['title'];
        $text = $_data['text'];
        $active = intval($_data['active']);
        $po_attributes = json_encode(
          array(
            'evaluate_x_prio' => strval(intval($evaluate_x_prio)),
            'only_x_prio' => strval(intval($only_x_prio))
          )
        );
        $stmt = $pdo->prepare("REPLACE INTO `pushover` (`username`, `key`, `attributes`, `senders`, `token`, `title`, `text`, `active`)
          VALUES (:username, :key, :po_attributes, :senders, :token, :title, :text, :active)");
        $stmt->execute(array(
          ':username' => $username,
          ':key' => $key,
          ':po_attributes' => $po_attributes,
          ':senders' => $senders,
          ':token' => $token,
          ':title' => $title,
          ':text' => $text,
          ':active' => $active
        ));
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data, $_data),
          'msg' => 'pushover_settings_edited'
        );
      }
    break;
    case 'get':
      if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      $stmt = $pdo->prepare("SELECT * FROM `pushover` WHERE `username` = :username");
      $stmt->execute(array(
        ':username' => $_data
      ));
      $data = $stmt->fetch(PDO::FETCH_ASSOC);
      $data['attributes'] = json_decode($data['attributes'], true);
      if (empty($data)) {
        return false;
      }
      else {
        return $data;
      }
    break;
    case 'test':
      if (!isset($_SESSION['acl']['pushover']) || $_SESSION['acl']['pushover'] != "1" ) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      if (!is_array($_data['username'])) {
        $usernames = array();
        $usernames[] = $_data['username'];
      }
      else {
        $usernames = $_data['username'];
      }
      foreach ($usernames as $username) {
        if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data),
            'msg' => 'access_denied'
          );
          continue;
        }
        $stmt = $pdo->prepare("SELECT * FROM `pushover`
          WHERE `username` = :username");
        $stmt->execute(array(
          ':username' => $username
        ));
        $api_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($api_data)) {
          $title = (!empty($api_data['title'])) ? $api_data['title'] : 'Mail';
          $text = (!empty($api_data['text'])) ? $api_data['text'] : 'You\'ve got mail 📧';
          curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL => "https://api.pushover.net/1/users/validate.json",
            CURLOPT_POSTFIELDS => array(
              "token" => $api_data['token'],
              "user" => $api_data['key']
            ),
            CURLOPT_SAFE_UPLOAD => true,
            CURLOPT_RETURNTRANSFER => true,
          ));
          $result = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close($ch);
          if ($httpcode == 200) {
            $_SESSION['return'][] = array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $_action, $_data),
              'msg' => sprintf('Pushover API OK (%d): %s', $httpcode, $result)
            );
          }
          else {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data),
              'msg' => sprintf('Pushover API ERR (%d): %s', $httpcode, $result)
            );
          }
        }
        else {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data),
            'msg' => 'pushover_credentials_missing'
          );
          return false;
        }
      }
    break;
  }
}