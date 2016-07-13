<?php
/**
 * This file is part of the Purl package.
 *
 * (c) pengzhile <pengzhile@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Purl;

class StreamParser
{
    protected $buffer = '';
    protected $packageLength = 0;
    protected $headerParsed = false;

    protected $version, $code, $msg;
    protected $headers;
    protected $body = '';
    protected $chuncked;

    public function tryParse($data)
    {
        $this->buffer .= $data;
        if (!$this->headerParsed) {
            $pos = strpos($this->buffer, "\r\n\r\n");
            if (0 === $pos) {
                return false;
            }

            if (false === $pos) {
                return null;
            }

            $this->parseHeader($data);

            if (!$this->chuncked) {
                if (null === $len = $this->getHeader('Content-Length')) {
                    return false;
                }

                $this->packageLength = (int)$len;
            }
        }

        if (!$this->chuncked) {
            if ($this->packageLength === strlen($this->buffer)) {
                return new Result($this->version, $this->code, $this->msg, $this->headers, $this->buffer);
            } else {
                return null;
            }
        }

        do {
            if (false === strpos($this->buffer, "\r\n")) {
                return null;
            }

            list($size, $content) = explode("\r\n", $this->buffer, 2);
            if (0 === $size = hexdec($size)) {
                return new Result($this->version, $this->code, $this->msg, $this->headers, $this->body);
            }

            if (strlen($content) < $size - 2) {
                return null;
            }

            $this->body .= substr($content, 0, $size);
            $this->buffer = substr($content, $size + 2);
        } while (true);

        return false;
    }

    protected function getHeader($key, $default = null)
    {
        return isset($this->headers[$key]) ? $this->headers[$key] : $default;
    }

    protected function parseHeader($data)
    {
        list($headerStr, $body) = explode("\r\n\r\n", $data, 2);

        $headersRaw = explode("\r\n", $headerStr);
        $responseLine = explode(' ', array_shift($headersRaw));
        list($this->version, $this->code, $this->msg) = $responseLine;

        $headers = array();
        foreach ($headersRaw as $headerRaw) {
            list($key, $value) = explode(': ', $headerRaw, 2);
            $headers[$key] = $value;
        }

        $this->headers = $headers;
        $this->buffer = $body;
        $this->chuncked = 'chunked' === strtolower($this->getHeader('Transfer-Encoding'));
        $this->headerParsed = true;
    }
}