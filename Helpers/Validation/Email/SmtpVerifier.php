<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * SMTP Verifier - Checks if an email mailbox actually exists
 *
 * Connects to the mail server and verifies if the mailbox exists
 * without sending an actual email (SMTP VRFY/RCPT TO commands)
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Validation\Email;

use Exception;

class SmtpVerifier
{
    private string $email;

    private string $domain;

    private int $timeout;

    private bool $debug;

    public function __construct(string $email, int $timeout = 10, bool $debug = false)
    {
        $this->email = $email;
        $this->timeout = $timeout;
        $this->debug = $debug;

        $parts = explode('@', $email, 2);
        $this->domain = $parts[1] ?? '';
    }

    /**
     * Verify if the email mailbox exists
     */
    public function verify(): bool
    {
        if (empty($this->domain)) {
            return false;
        }

        try {
            $mxHosts = $this->getMxRecords();

            if (empty($mxHosts)) {
                $this->log("No MX records found for {$this->domain}");

                return false;
            }

            foreach ($mxHosts as $mxHost) {
                $result = $this->checkMailbox($mxHost);

                if ($result !== null) {
                    return $result;
                }
            }

            $this->log('All MX hosts failed to respond, assuming valid');

            return true;
        } catch (Exception $e) {
            $this->log('SMTP verification failed: '.$e->getMessage());

            return true;
        }
    }

    private function getMxRecords(): array
    {
        $mxRecords = [];

        if (getmxrr($this->domain, $mxHosts, $mxWeights)) {
            array_multisort($mxWeights, SORT_ASC, $mxHosts);
            $mxRecords = $mxHosts;
        } else {
            $mxRecords = [$this->domain];
        }

        return $mxRecords;
    }

    private function checkMailbox(string $mxHost): ?bool
    {
        $socket = null;

        try {
            $socket = @fsockopen($mxHost, 25, $errno, $errstr, $this->timeout);

            if (! $socket) {
                $this->log("Failed to connect to {$mxHost}: {$errstr}");

                return null;
            }

            stream_set_timeout($socket, $this->timeout);

            $response = $this->readResponse($socket);
            if (! $this->isPositiveResponse($response)) {
                $this->log("Invalid greeting from {$mxHost}: {$response}");
                fclose($socket);

                return null;
            }

            $this->sendCommand($socket, 'EHLO '.gethostname());
            $response = $this->readResponse($socket);

            if (! $this->isPositiveResponse($response)) {
                $this->sendCommand($socket, 'HELO '.gethostname());
                $response = $this->readResponse($socket);

                if (! $this->isPositiveResponse($response)) {
                    fclose($socket);

                    return null;
                }
            }

            $this->sendCommand($socket, 'MAIL FROM:<verify@'.gethostname().'>');
            $response = $this->readResponse($socket);

            if (! $this->isPositiveResponse($response)) {
                fclose($socket);

                return null;
            }

            $this->sendCommand($socket, "RCPT TO:<{$this->email}>");
            $response = $this->readResponse($socket);

            $this->sendCommand($socket, 'QUIT');
            fclose($socket);

            if ($this->isPositiveResponse($response)) {
                $this->log("Mailbox exists: {$this->email}");

                return true;
            } elseif ($this->isNegativeResponse($response)) {
                $this->log("Mailbox does not exist: {$this->email}");

                return false;
            } else {
                $this->log("Uncertain response: {$response}");

                return null;
            }
        } catch (Exception $e) {
            $this->log('Error checking mailbox: '.$e->getMessage());
            if ($socket) {
                @fclose($socket);
            }

            return null;
        }
    }

    private function sendCommand($socket, string $command): void
    {
        $this->log(">> {$command}");
        fwrite($socket, $command."\r\n");
    }

    private function readResponse($socket): string
    {
        $response = '';

        while ($line = fgets($socket, 515)) {
            $response .= $line;

            // Check if this is the last line (format: "250 OK" or "250-Continue")
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }

        $this->log('<< '.trim($response));

        return $response;
    }

    private function isPositiveResponse(string $response): bool
    {
        return preg_match('/^[23]\d{2}/', $response) === 1;
    }

    private function isNegativeResponse(string $response): bool
    {
        return preg_match('/^5\d{2}/', $response) === 1;
    }

    private function log(string $message): void
    {
        if ($this->debug) {
            echo "[SMTP] {$message}\n";
        }
    }
}
