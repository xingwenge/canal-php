<?php
namespace client;

class TcpClient
{

    /**
     * Handle to PHP socket
     *
     * @var resource
     */
    private $handle_ = null;

    /**
     * Remote hostname
     *
     * @var string
     */
    protected $host_ = 'localhost';

    /**
     * Remote port
     *
     * @var int
     */
    protected $port_ = '9090';

    /**
     * Send timeout in milliseconds
     *
     * @var int
     */
    private $sendTimeout_ = 100;

    /**
     * Recv timeout in milliseconds
     *
     * @var int
     */
    private $recvTimeout_ = 750;
    
    /**
     * connect time out
     * @var unknown
     */
    private $connectTimeout_ = 1000;

    /**
     * Is send timeout set?
     *
     * @var bool
     */
    private $sendTimeoutSet_ = false;

    /**
     * Persistent socket or plain?
     *
     * @var bool
     */
    private $persist_ = false;

    /**
     * Socket constructor
     *
     * @param string $host
     *            Remote hostname
     * @param int $port
     *            Remote port
     * @param bool $persist
     *            Whether to use a persistent socket
     * @param string $debugHandler
     *            Function to call for error logging
     */
    public function __construct($host = 'localhost', $port = 9090, $persist = false)
    {
        $this->host_ = $host;
        $this->port_ = $port;
        $this->persist_ = $persist;
    }

    /**
     *
     * @param resource $handle
     * @return void
     */
    public function setHandle($handle)
    {
        $this->handle_ = $handle;
    }

    /**
     * Sets the send timeout.
     *
     * @param int $timeout
     *            Timeout in milliseconds.
     */
    public function setSendTimeout($timeout)
    {
        $this->sendTimeout_ = $timeout;
    }

    /**
     * Sets the receive timeout.
     *
     * @param int $timeout
     *            Timeout in milliseconds.
     */
    public function setRecvTimeout($timeout)
    {
        $this->recvTimeout_ = $timeout;
    }

    /**
     * @return unknown
     */
    public function getConnectTimeout_()
    {
        return $this->connectTimeout_;
    }

    /**
     * @param $connectTimeout_
     */
    public function setConnectTimeout_($connectTimeout_)
    {
        $this->connectTimeout_ = $connectTimeout_;
    }

    /**
        * Get the host that this socket is connected to
        *
        * @return string host
        */
    public function getHost()
    {
        return $this->host_;
    }

    /**
     * Get the remote port that this socket is connected to
     *
     * @return int port
     */
    public function getPort()
    {
        return $this->port_;
    }

    /**
     * Tests whether this is open
     *
     * @return bool true if the socket is open
     */
    public function isOpen()
    {
        return is_resource($this->handle_);
    }

    /**
     * Connects the socket.
     */
    public function open()
    {
        if ($this->isOpen()) {
            throw new \Exception('Socket already connected');
        }

        if (empty($this->host_)) {
            throw new \Exception('Cannot open null host');
        }
        
        if ($this->port_ <= 0) {
            throw new \Exception('Cannot open without port');
        }

        if ($this->persist_) {
            $this->handle_ = @pfsockopen($this->host_, $this->port_, $errno, $errstr, $this->connectTimeout_ / 1000.0);
        } else {
            $this->handle_ = @fsockopen($this->host_, $this->port_, $errno, $errstr, $this->connectTimeout_ / 1000.0);
        }

        // Connect failed?
        if ($this->handle_ === false) {
            $error = 'Socket: Could not connect to ' . $this->host_ . ':' . $this->port_ . ' (' . $errstr . ' [' . $errno . '])';
            throw new \Exception($error, 10);
        }

        stream_set_timeout($this->handle_, 0, $this->sendTimeout_);
        $this->sendTimeoutSet_ = true;
    }

    /**
     * Closes the socket.
     */
    public function close()
    {
        if (! $this->persist_) {
            @fclose($this->handle_);
            $this->handle_ = null;
        }
    }

    /**
     * Uses stream get contents to do the reading
     *
     * @param int $len
     *            How many bytes
     * @return string Binary data
     */
    public function readAll($len)
    {
        if ($this->sendTimeoutSet_) {
            stream_set_timeout($this->handle_, 0, $this->recvTimeout_ * 1000);
            $this->sendTimeoutSet_ = false;
        }
        // This call does not obey stream_set_timeout values!
        // $buf = @stream_get_contents($this->handle_, $len);

        $pre = null;
        while (true) {
            $buf = @fread($this->handle_, $len);
            if ($buf === false || $buf === '') {
                $md = stream_get_meta_data($this->handle_);
                if ($md['timed_out']) {
                    throw new \Exception('TSocket: timed out reading ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
                } else {
                    throw new \Exception('TSocket: Could not read ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
                }
            } elseif (($sz = strlen($buf)) < $len) {
                $md = stream_get_meta_data($this->handle_);
                if ($md['timed_out']) {
                    throw new \Exception('TSocket: timed out reading ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
                } else {
                    $pre .= $buf;
                    $len -= $sz;
                }
            } else {
                return $pre . $buf;
            }
        }
    }

    /**
     * Read from the socket
     *
     * @param int $len
     *            How many bytes
     * @return string Binary data
     */
    public function read($len)
    {
        if ($this->sendTimeoutSet_) {
            stream_set_timeout($this->handle_, 0, $this->recvTimeout_ * 1000);
            $this->sendTimeoutSet_ = false;
        }
        $data = fread($this->handle_, $len);
        if ($data === false || $data === '') {
            $md = stream_get_meta_data($this->handle_);
            if ($md['timed_out']) {
                throw new \Exception('TSocket: timed out reading ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
            } else {
                throw new \Exception('TSocket: Could not read ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
            }
        }
        return $data;
    }

    /**
     * Write to the socket.
     *
     * @param string $buf
     *            The data to write
     */
    public function write($buf)
    {
        if (! $this->sendTimeoutSet_) {
            stream_set_timeout($this->handle_, 0, $this->sendTimeout_ * 1000);
            $this->sendTimeoutSet_ = true;
        }
        while (strlen($buf) > 0) {
            $got = @fwrite($this->handle_, $buf);
            if ($got === 0 || $got === false) {
                $md = stream_get_meta_data($this->handle_);
                if ($md['timed_out']) {
                    throw new \Exception('TSocket: timed out writing ' . strlen($buf) . ' bytes from ' . $this->host_ . ':' . $this->port_);
                } else {
                    throw new \Exception('TSocket: Could not write ' . strlen($buf) . ' bytes ' . $this->host_ . ':' . $this->port_);
                }
            }
            $buf = substr($buf, $got);
        }
    }

    /**
     * Flush output to the socket.
     */
    public function flush()
    {
        $ret = fflush($this->handle_);
        if ($ret === false) {
            throw new \Exception('TSocket: Could not flush: ' . $this->host_ . ':' . $this->port_);
        }
    }

    /**
     * 获取数据
     *
     * @param
     *            $writeBinaryData
     * @return binaryData 返回: 版本号（1位）+ 数据长度(4位)， 消息实体(数据长度-5)
     */
    public function request($writeBinaryData)
    {
        try {
            if (! $this->isOpen()) {
                $this->open();
            }

            // 写数据
            $checkHeader = array(
                18,
                17,
                13,
                10,
                9
            );
            $checkFooter = array(
                9,
                10,
                13,
                17,
                18
            );

            $s = null;
            $e = null;
            foreach ($checkHeader as $v) {
                $s = $s . pack('C', trim($v));
            }
            $e = null;
            foreach ($checkFooter as $v) {
                $e = $e . pack('C', trim($v));
            }
            $writeBinaryData = $s . $writeBinaryData . $e;
            $this->write($writeBinaryData);

            // 整个数据流： 固定头(5位) + 版本号（1位）+ 数据长度(4位)， 消息实体(数据长度-5), 固定尾部(5位)
            // 返回: 版本号（1位）+ 数据长度(4位)， 消息实体(数据长度-5)
            // 读数据
            $header = $this->read(10);
            $responseHeaerArr = unpack('c*', $header);

            // 校验头部
            if (! $this->_checkArray($checkHeader, array_slice($responseHeaerArr, 0, 5))) {
                throw new \Exception('Check header failure!');
            }
            // 数据长度
            $fetchLen = $this->_bytesToInteger($responseHeaerArr, 7);
            try {
                $l =  $this->_getLimit($fetchLen);
                if ($fetchLen > $l) {
                    throw new \Exception('get stream error, len exceed limit , len is ' . $fetchLen . 'limit is ' . $l);
                }
            } catch (\Exception $e) {
                throw $e;
            }
            $responseBinaryData = $this->readAll($fetchLen);
            // 校验尾部
            $bodyArr = unpack('c*', substr($responseBinaryData, $fetchLen - 5));
            if (! $this->_checkArray($checkFooter, array_slice($bodyArr, 0, 5))) {
                throw new \Exception('Check footer failure!');
            }

            // 返回
            // $returnBinary = null;

            $returnBinary = substr($header, 5) . substr($responseBinaryData, 0, $fetchLen - 5);
            return $returnBinary;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @param
     *            $arr1
     * @param
     *            $arr2
     * @return bool
     */
    private function _checkArray($arr1, $arr2)
    {
        return $arr1 == $arr2;
    }

    private function _getLimit($len)
    {
        try {
            $limit = ini_get('memory_limit');
            if ($limit == -1) {
                return 2147483647;
            }
            $arr = '';
            $b = preg_match('/\d+/', $limit, $arr);
            if ($b) {
                $res = $arr[0];
                $unit = substr($limit, strlen($res));
                if (stristr($unit, 'M')) {
                    return $res * 1024 * 8 * 0.8;
                }
                if (stristr($unit, 'G')) {
                    return $res * 1024 * 1024 * 8 * 0.8;
                } else {
                    return $res * 8 * 0.8;
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function _bytesToInteger($bytes, $position)
    {
        $val = 0;
        $val = $bytes[$position + 3] & 0xff;
        $val <<= 8;
        $val |= $bytes[$position + 2] & 0xff;
        $val <<= 8;
        $val |= $bytes[$position + 1] & 0xff;
        $val <<= 8;
        $val |= $bytes[$position] & 0xff;
        return $val;
    }
}
