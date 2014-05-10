<?php
namespace Talk2Me;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    private $rooms;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        //echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
        //    , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        $json = json_decode($msg);

        // Handle login
        if ($json->a == "login") {
            $this->rooms[$from->resourceId] = array("room"=>$json->room, "username"=>$json->username);
            return;
        }

        if ($json->a == "message") {
            foreach ($this->clients as $client) {
                if ($from !== $client 
                        // Ensure message is sent to the proper room.
                        && $this->rooms[$from->resourceId]['room'] == $this->rooms[$client->resourceId]['room']) {
                    // The sender is not the receiver, send to each client connected
                    $o = array("status"=>"ok", "a"=>"message", "msg"=>$json->msg);
                    $client->send(json_encode($o));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
        foreach ($this->clients as $client) {
            $o = array("status"=>"ok", "a"=>"message", 
                    "msg"=>"<span style=\"color:red;\">@" 
                    . $this->rooms[$conn->resourceId]['username'] 
                    . " has disconnected.</span>");
            $client->send(json_encode($o));
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}
