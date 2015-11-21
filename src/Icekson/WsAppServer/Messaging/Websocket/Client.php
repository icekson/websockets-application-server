<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL ^ E_WARNING ^ E_STRICT ^ E_NOTICE);
namespace Icekson\WsAppServer\Messaging\Websocket;
/**
 * Very basic websocket client.
 *
 * @author Simon Samtleben <web@lemmingzshadow.net>
 * @author Walter Stanish <stani.sh/walter>
 */
class Client
{
    private $_Socket = null;
    private $_debugging = 0;

    # frame decode-related buffers
    private $_partial_message = '';        # partial message from previous frame
    private $_last_opcode_type = '';    # previous opcode for fragments

    public function __construct($host, $port, $path = '/')
    {
        $this->_connect($host, $port, $path);
    }

    public function __destruct()
    {
        $this->_disconnect();
    }

    private function debug($str)
    {
        if ($this->_debugging) {
            if ($fh = fopen(APP . 'logs/WebSocketClient.log', 'a')) {
                fwrite($fh, $str);
                fclose($fh);
            }
        }
    }

    # get/set function
    public function debugging($value = '')
    {
        if ($value != '') {
            if ($value) {
                $this->_debugging = 1;
            } else {
                $this->_debugging = 0;
            }
        }
        return $value;
    }

    # hex dump helper function
    public function _hex_dump($data, $newline = "\n")
    {
        $output = '';
        static $from = '';
        static $to = '';

        static $width = 16; # number of bytes per line

        static $pad = '.'; # padding for non-visible characters

        if ($from === '') {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width * 2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        foreach ($hex as $i => $line) {
            $output .= sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
            $offset += $width;
        }
        return $output;
    }

    # Get data from the socket.
    #
    #  Under the original design, this function was
    #  to return immediately the results of a single
    #  socket read / frame decode. However, because
    #  of the requirements for a buffer (due to
    #  multiframe messages / message continuation),
    #  the function is no longer assured to return
    #  data (as a single frame may not include an
    #  entire message).
    #
    # Returns:
    #  Zero or more messages in an array structure
    #  in case of successful decode (the frame
    #  read may have been a control frame - ie:
    #  no messages per se - or a fragmented message
    #  that remains incomplete), or throws an
    #  exception in case of failure.
    public function getData()
    {
        if (!($wsdata = fread($this->_Socket, 2000))) {
            throw new \Exception('Socket read failed.');
        }
        return $this->_decodeFrame($wsdata);
    }


# send data to the socket
    public function send($data, $waitResponse = true)
    {
        $res = @fwrite($this->_Socket, $this->_encodeFrame($data));
        if($waitResponse) {
            $wsData = fread($this->_Socket, 2000);
            return $this->_decodeFrame($wsData);
        }
        return null;
    }

    private function _connect($host, $port, $path)
    {
        $key = base64_encode($this->_generateRandomString(16, false, true));

        $header = "GET " . $path . " HTTP/1.1\r\n";
        $header .= "Host: " . $host . ":" . $port . "\r\n";
        $header .= "Upgrade: websocket\r\n";
        $header .= "Connection: Upgrade\r\n";
        $header .= "Origin: null\r\n";
        $header .= "Sec-WebSocket-Key: " . $key . "\r\n";
        $header .= "Sec-WebSocket-Origin: null\r\n";
        $header .= "Sec-WebSocket-Version: 8\r\n";
        $header .= "\r\n";
        if($this->_debugging) {
            print "Connecting... ";
        }
        $this->_Socket = fsockopen($host, $port, $errno, $errstr, 2);
        if($this->_debugging) {
            print "OK.\n";
            print "Sending data... ";
        }
        fwrite($this->_Socket, $header) or die('Error: ' . $errno . ':' . $errstr);
        if($this->_debugging) {
            print "OK.\n------------sent this-----------------------\n$header\n-----------------------------------\n";
            print "Lengthening socket read timeout to 10 seconds... ";
        }
        if (stream_set_timeout($this->_Socket, 10)) {
            if($this->_debugging) {
                print "OK.\n";
            }
        } else {
            if($this->_debugging) {
                print "FAILED.\n";
            }
        }
        if($this->_debugging) {
            print "Reading response... ";
        }
        if (!($response = fread($this->_Socket, 2000))) {
            if($this->_debugging) {
                print "ERROR: No response.\n";
            }
            return false;
        }
        if($this->_debugging) {
            print "OK.\n";
        }
        if($this->_debugging) {
            print_r($response);
        }

        preg_match('#Sec-WebSocket-Accept:\s(.*)$#mU', $response, $matches);
        $keyAccept = trim($matches[1]);
        $expectedResponse = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $this->debug("Comparing $keyAccept to $expectedResponse!\n");
        return ($keyAccept === $expectedResponse) ? true : false;

    }

    private function _disconnect()
    {
        fclose($this->_Socket);
    }

    private function _generateRandomString($length = 10, $addSpaces = true, $addNumbers = true)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"??$%&/()=[]{}';
        $useChars = array();
        // select some random chars:
        for ($i = 0; $i < $length; $i++) {
            $useChars[] = $characters[mt_rand(0, strlen($characters) - 1)];
        }
        // add spaces and numbers:
        if ($addSpaces === true) {
            array_push($useChars, ' ', ' ', ' ', ' ', ' ', ' ');
        }
        if ($addNumbers === true) {
            array_push($useChars, rand(0, 9), rand(0, 9), rand(0, 9));
        }
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, $length);
        return $randomString;
    }

# Encode a frame
    private function _encodeFrame($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $frame = '';
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'ping':
                // first byte indicates FIN, Ping frame
                // (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame
                // (10001010):
                $frameHead[0] = 138;
                break;

            case 'text':
                // first byte indicates FIN, Text-Frame
                // (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (return false if
            // to much data)
            if ($frameHead[2] > 127) {
                return false;
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked === true) {
            // generate a random mask:
            $mask = array();
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        $framePayload = array();
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^
                $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

# Frame decoding function.
#  - See the 'Data Framing' section in the specification
#     - All data from a client to a server is 'masked' to avoid confusing intermediaries and for security reasons
#     - No data from a server to a client is masked
#        - The client must close the connection upon receiving a
#          masked frame (as per draft 16)
#  - Frame format (as per draft 16):
#       0                   1                   2                   3
#       0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
#      +-+-+-+-+-------+-+-------------+-------------------------------+
#      |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
#      |I|S|S|S|  (4)  |A|     (7)     |             (16/63)           |
#      |N|V|V|V|       |S|             |   (if payload len==126/127)   |
#      | |1|2|3|       |K|             |                               |
#      +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
#      |     Extended payload length continued, if payload len == 127  |
#      + - - - - - - - - - - - - - - - +-------------------------------+
#      |                               |Masking-key, if MASK set to 1  |
#      +-------------------------------+-------------------------------+
#      | Masking-key (continued)       |          Payload Data         |
#      +-------------------------------- - - - - - - - - - - - - - - - +
#      :                     Payload Data continued ...                :
#      + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
#      |                     Payload Data continued ...                |
#      +---------------------------------------------------------------+
#
# BUGS: This implementation is partial and does not conform to the
#       specification. Specifically, some control frames are not
#       supported.
#       The decode function should also include an expectation
#       flag that defines expected masking behaviour (ie: client to
#       server packet being decoded on the server, or server to
#       client packet being decoded on the client)
    private function _decodeFrame($raw_frame)
    {

        $this->debug("_decodeFrame():
 - raw frame:
-------------------------
$raw_frame
-------------------------
 - hex dump:
-------------------------
" . $this->_hex_dump($raw_frame) . "
-------------------------\n");

        # For simplicity of implementation, we ignore byte one.
        # This means:
        #  - We do not support multi-frame messages
        #  - Reserved flag behaviour mandated in the specification
        #    cannot be implemented
        #  - We cannot distinguish between control frames and data
        #    frames.
        $first_byte = sprintf('%08b', ord($raw_frame[0]));
        #  - Determine FIN status
        $flag_fin = $first_byte[0];
        $flag_rsv1 = $first_byte[1];
        $flag_rsv2 = $first_byte[2];
        $flag_rsv3 = $first_byte[3];
        $opcode = substr($first_byte, 4);
        $this->debug(" - First byte ($first_byte) in binary: " . sprintf('%08b', ord($raw_frame[0])) . "\n");
        $this->debug("    - FIN    = $flag_fin\n");
        $this->debug("    - RSV1   = $flag_rsv1\n");
        $this->debug("    - RSV2   = $flag_rsv2\n");
        $this->debug("    - RSV3   = $flag_rsv3\n");
        if ($flag_rsv1 || $flag_rsv2 || $flag_rsv3) {
            $this->debug("ERROR: Unknown RSV<x> flag set!\n");
            # disabled for now
            #$this->_disconnect();
            #return false;
        }

        # Determine opcode type
        if ($opcode == '0000') {
            $opcode_type = 'Continuation';
            if ($this->_last_opcode_type == '') {
                $this->debug("WARNING: Continuation frame received without context!\n");
                # disabled for now
                #$this->_disconnect;
                #return false;
                # temporary workaround: pretend we had a text frame earlier
                $this->_last_opcode_type = 'Text';
            }
        } elseif ($opcode == '0001') {
            $opcode_type = 'Text';
        } elseif ($opcode = '0002') {
            $opcode_type = 'Binary';
            # Fix for strange mtgox.com behavior (fragmented text message
            # subsequent frames marked as Binary, RSV1 && RSV2)
            if ($flag_rsv1 == 1 && $flag_rsv2 == 1 && flag_rsv3 == 0) {
                $opcode_type = 'Text';
            }
        } elseif ($opcode == '1000') {
            $opcode_type = 'Connection close';
        } elseif ($opcode = '1001') {
            $opcode_type = 'Ping';
            $this->debug("WARNING: Ping functionality is not supported by this implementation.");
        } elseif ($opcode = '1010') {
            $opcode_type = 'Pong';
        } else {
            $opcode_type = 'Unknown/Reserved';
        }
        $this->debug("    - Opcode = $opcode ($opcode_type)\n");

        # Disconnect
        if (($opcode_type == 'Connection close')) {
            $this->_disconnect();
            return false;
        }

        # Examine the second byte (mask, payload length)
        $second_byte = sprintf('%08b', ord($raw_frame[1]));
        $this->debug(" - Second byte ($second_byte) in binary: " . sprintf('%08b', ord($raw_frame[1])) . "\n");

        #  - Determine mask status
        $frame_is_masked = $second_byte[0];
        $this->debug("    - Masked = $frame_is_masked\n");

        #  - Determine payload length
        $payload_length = $frame_is_masked ? ord($raw_frame[1]) & 127 : ord($raw_frame[1]);
        $this->debug("    - Payload Length = $payload_length\n");

        # Further processing is based upon masking state.
        #
        # Masked frame (client to server)
        if ($frame_is_masked) {

            # First we determine the mask and payload offsets

            # Default (standard payload, 7 bits)
            $mask_offset = 2;
            $payload_offet = 6;

            # Extended payload (7+16 bits or +2 bytes)
            if ($payload_length === 126) {
                $mask_offset = 4;
                $payload_offset = 8;
            } # Really extended payload (7+64 bits or +8 bytes)
            elseif ($payload_length === 127) {
                $mask_offset = 10;
                $payload_offset = 14;
            }

            # Now we extract the mask and payload
            $mask = substr($raw_frame, $mask_offset, 4);
            $encoded_payload = substr($raw_frame, $payload_offset);

            # Finally, we decode the encoded frame payload
            for ($i = 0; $i < strlen($encoded_payload); $i++) {
                $payload .= $encoded_payload[$i] ^ $mask[$i % 4];
            }
        } # Unmasked frame (server to client)
        else {
            # Default payload offset
            $payload_offset = 2;

            # Extended payload (7+16 bits or +2 bytes)
            if ($payload_length === 126) {
                $payload_offset = 4;
            } # Really extended payload (7+64 bits or +8 bytes)
            elseif ($payload_length === 127) {
                $payload_offset = 10;
            }

            # Read the payload
            $payload = substr($raw_frame, $payload_offset - 2, strlen($raw_frame) - $payload_offset + 2);
        }

        # Verify payload length
        if ($payload_length != strlen($payload)) {
            $this->debug("WARNING: Observed payload length (" . strlen($payload) . " bytes) differs from claimed payload length ($payload_length bytes).\n");
        }

        # If the frame is of type 'text', or this is a continuation
        # of a fragmented text frame, then potentially split the
        # content in to separate messages and/or message portions
        if ($opcode_type == 'Text' || ($opcode_type == 'Continuation' && $this->_last_opcode_type == 'Text')) {

            # Potentially split the content
            $message_portions = explode(pack('H*', 'FF00'), $payload);

            # If the first message (portion) begins with an 00 (null) byte, remove it.
            if (substr($message_portions[0], 0, 1) == 0x00) {
                $message_portions[0] = substr($message_portions[0], 1);
            }

            # If we have an outstanding partial message, then we can
            # prepend the outstanding partial message to the first
            # message of the current frame.
            if ($this->_partial_message != '') {
                $this->debug("Gluing remembered partial message to beginning of initial message portion.\n");
                # prepend
                $message_portions[0] = $this->_partial_message . $message_portions[0];
                $this->debug($this->_hex_dump($message_portions[0]));
                # unset
                $this->_partial_message = '';
            }

            # If the final message (portion) ends with an FF byte, we can
            # remove it since the message is complete.
            $final = count($message_portions) - 1;
            if (ord(substr($message_portions[$final], -1)) == 255) {
                $this->debug(" - Text frame summary: No partial messages in frame (complete frame).\n");
                $message_portions[$final] = substr($message_portions[$final], 0, strlen($message_portions[$final]) - 1);
            } # The final message is incomplete.
            else {
                $this->debug(" - Saving partial final message-portion for subsqeuent frame.\n");
                $this->debug($this->_hex_dump($message_portions[$final]) . "\n");
                # remember
                $this->_partial_message = $message_portions[$final];
                $this->debug($this->_hex_dump($this->partial_message));
                # unset
                unset($message_portions[$final]);
            }
            # Overkill for debugging!
            #$i=0;
            #foreach($message_portions as $message_portion) {
            # $this->debug("==== message portion #{$i} ====\n");
            # $this->debug($this->_hex_dump($message_portion) . "\n");
            # $this->debug("===============================\n");
            # $i++;
            #}
        }
        # otherwise, if the frame is of type 'Binary', set the
        # first message portion to the entire payload
        elseif ($opcode_type == 'Binary') {
            $message_portions = array($payload);
        }
        # otherwise, we return an empty array since decoding
        # was successful but no message or message-portion was
        # present within the frame (eg: control frames, etc.)
        else {
            $message_portions = array();
        }

        # debug
        $this->debug(print_r($message_portions, 1));

        # Except in the case of continued fragements,
        # remember the opcode type for next time (fragements, etc.)
        if ($opcode_type != 'Continuation') {
            $this->_last_opcode_type = $opcode_type;
        }

        # Return the array of message portions
        return $message_portions;

    }
}