<?php

namespace Icinga\Module\Jira;

use RuntimeException;

class LegacyCommandPipe
{
    protected $pipe;

    public function __construct($pipe)
    {
        $this->pipe = $pipe;
    }

    public function acknowledge($author, $message, $host, $service)
    {
        $pipe = $this->pipe;
        if (@file_exists($pipe)) {
            if (@is_writable($pipe)) {
                $result = file_put_contents($pipe, $this->renderAck($author, $message, $host, $service));

                return $result !== false;
            } else {
                throw new RuntimeException(\sprintf(
                    '%s is not writable, cannot send %s',
                    $pipe,
                    $this->shorten($message, 60)
                ));
            }
        } else {
            throw new RuntimeException(\sprintf(
                '%s does not exist, cannot send %s',
                $pipe,
                $this->shorten($message, 60)
            ));
        }
    }

    protected function shorten($string, $maxLength)
    {
        if (strlen($string) > $maxLength) {
            return substr($string, 0, $maxLength) . '...';
        } else {
            return $string;
        }
    }

    protected function renderAck(
        $author,
        $comment,
        $host,
        $service = null,
        $notify = false,
        $sticky = false,
        $persistent = false,
        $expireTime = null
    ) {
        if ($expireTime === null) {
            $expireSuffix = '';
        } else {
            $expireSuffix = '_EXPIRE';
        }

        $cmd = sprintf('[%u] ', time());
        if ($service === null) {
            $cmd .= "ACKNOWLEDGE_HOST_PROBLEM$expireSuffix;$host";
        } else {
            $cmd .= "ACKNOWLEDGE_SVC_PROBLEM$expireSuffix;$host;$service";
        }


        $cmd .= sprintf(
            ';%u;%u;%u',
            $sticky ? '2' : '0',
            (string) $notify,
            $persistent
        );
        if ($expireTime !== null) {
            $cmd .= ";$expireTime";
        }

        $cmd .= ";$author;$comment\n";

        return $cmd;
    }
}
