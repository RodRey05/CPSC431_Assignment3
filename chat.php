<?php
session_start();
ob_start();
header("Content-type: application/json");
date_default_timezone_set('UTC');
//connect to database
$db = mysqli_connect('mariadb', 'cs431s21', 'ohV3ooh0', 'cs431s21');
if (mysqli_connect_errno()) {
   echo '<p>Error: Could not connect to database.<br/>
   Please try again later.</p>';
   exit;
}
//helper funtion to replace get_results() if without mysqlnd 
function get_result( $Statement ) {
    $RESULT = array();
    $Statement->store_result();
    for ( $i = 0; $i < $Statement->num_rows; $i++ ) {
        $Metadata = $Statement->result_metadata();
        $PARAMS = array();
        while ( $Field = $Metadata->fetch_field() ) {
            $PARAMS[] = &$RESULT[ $i ][ $Field->name ];
        }
        call_user_func_array( array( $Statement, 'bind_result' ), $PARAMS );
        $Statement->fetch();
    }
    return $RESULT;
}
try { 
    $currentTime = time();
    $session_id = session_id();    
    $lastPoll = isset($_SESSION['last_poll']) ? $_SESSION['last_poll'] : $currentTime;    
    $action = isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] == 'POST') ? 'send' : 'poll';
    switch($action) {
        case 'poll':
           $query = "SELECT * FROM chatlog WHERE date_created >= ".$lastPoll;
           $stmt = $db->prepare($query);
           $stmt->execute();
           $stmt->bind_result($id, $message, $session_id, $date_created, $chat_usrname);
           $result = get_result( $stmt);
           $newChats = [];
           while($chat = array_shift($result)) {
               
               if($session_id == $chat['sent_by']) {
                  $chat['sent_by'] = 'self';
               } else {
                  $chat['sent_by'] = $chat['chat_usrname'];
               }
             
               $newChats[] = $chat;
            }
           $_SESSION['last_poll'] = $currentTime;

           print json_encode([
                'success' => true,
		'messages' => $newChats
           ]);
           exit;
        case 'send':
			$chat_usrname = isset($_POST['chat_usrname']) ? $_POST['chat_usrname'] : ' ';
            $message = isset($_POST['message']) ? $_POST['message'] : ''; 
			$chat_usrname = strip_tags($chat_usrname);
            $message = strip_tags($message);
			// insert values to the table
            $query = "INSERT INTO chatlog (message, sent_by, date_created, chat_usrname) VALUES('$message', '$session_id', '$currentTime', '$chat_usrname')";
				// check if record was processed sucessfully
				if (mysqli_query($db, $query))
				{
					echo " Congrats! Info was processed successfully";
				}
				else  //  ERROR: Das ist nicht so gut!
				{
					echo "Houston we have a PROBLEM " .$query. " " . mysqli_error($conn);
				}
				
            print json_encode(['success' => true]);
            exit;
    }
} catch(Exception $e) {
    print json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
