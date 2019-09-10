<?php

namespace Laravel\Serverless\Kubeless;

use Closure;
use Laravel\Serverless\HeadersParser;
use Symfony\Component\HttpFoundation\Response;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Prometheus\RenderTextFormat;
use Prometheus\Histogram;
use Prometheus\Counter;

class Proxy
{
    /** @var CollectorRegistry */
    protected $registry;
    /** @var Histogram */
    protected $durations;
    /** @var Counter */
    protected $calls;
    /** @var Counter */
    protected $fails;

    public static function start(Closure $callback)
    {
        return new static($callback);
    }

    private function __construct(Closure $callback)
    {
        $handler    = env('FUNC_HANDLER');
        $runtime    = env('FUNC_RUNTIME');
        $memLimit   = env('FUNC_MEMORY_LIMIT');
        $port       = env('FUNC_PORT')      ?? 8080;
        $timeout    = env('FUNC_TIMEOUT')   ?? 180;

        $context = [
            'function-name'     => $handler,
            'runtime'           => $runtime,
            'timeout'           => $timeout,
            'memory-limit'      => $memLimit
        ];

        $socket = stream_socket_server("tcp://0.0.0.0:$port", $errno, $errstr);

        if (! $socket) {
            $this->log("$errstr ($errno)");
            exit(1);
        }

        pcntl_signal(SIGCHLD, SIG_IGN);
        $this->initMonitoring();

        $this->log('Accepting connections');
        while ($conn = stream_socket_accept($socket, -1)) {
            $pid = pcntl_fork();
            if (-1 === $pid || $pid > 0) {
                fclose($conn);
            } else {
                $this->handle($context, $conn, $callback);
                exit();
            }
        }

        $this->log('Gracefully terminating');
        while (-1 !== pcntl_wait($status));
    }

    private function initMonitoring()
    {
        $this->registry = new CollectorRegistry(new APC());

        $this->durations = $this->registry->registerHistogram(
            'function',
            'duration_seconds',
            'Duration of user function in seconds',
            ['method']
        );

        $this->calls = $this->registry->registerCounter(
            'function',
            'calls_total',
            'Number of calls to user function',
            ['method']
        );

        $this->fails = $this->registry->registerCounter(
            'function',
            'failures_total',
            'Number of exceptions in user function',
            ['method']
        );
    }

    private function handle(array $context, $conn, $callback) : void
    {
        $requestline = fgets($conn, 4096);
        list($method, $path, $proto) = explode(' ', trim($requestline), 3);

        if ('GET' == strtoupper($method) && '/healthz' == strtolower($path)) {
            $this->healthz($conn, $proto);
            return;
        }

        if ('GET' === strtoupper($method) && '/metrics' === strtolower($path)) {
            $this->metrics($conn, $proto);
            return;
        }

        $headers = [];
        while ($headerline = trim(fgets($conn, 4096))) {
            // todo: check for truncation
            if (false === strpos($headerline, ':')) {
                $this->log("Malformed header: ${headerline}");
            } else {
                $headers[] = $headerline;
            }
        }

        $headers = HeadersParser::parse($headers);
        $event = [];

        foreach ($headers as $headerName => $values) {
            if (0 === strpos($headerName, 'event-')) {
                $event[$headerName] = current($values);
                unset($headers[$headerName]);
            }
        }

        $body = null;
        if (array_key_exists('content-length', $headers)) {
            // todo: validate it!
            $contentLength = current($headers['content-length']);
            $body = fopen('php://temp', 'wb+');
            stream_copy_to_stream($conn, $body, $contentLength);
            rewind($body);

            if (array_key_exists('content-type', $headers)) {
                $contentType = current($headers['content-type']);
                if ('application/x-www-form-urlencoded' == $contentType) {
                    parse_str(stream_get_contents($body), $body);
                }
                if ('application/json' === $contentType) {
                    $body = json_decode(stream_get_contents($body), true);
                }
            }
        }

        $event['data'] = $body;
        $event['extensions']['request'] = [
            'method'    => $method,
            'proto'     => $proto,
            'path'      => $path,
            'headers'   => $headers,
        ];

        $functionStarted = microtime(true);
        $this->calls->inc(['method' => $method]);
        try {
            // todo: timeout
            $result = $callback($event, $context, $this);
        } catch (\Exception $e) {
            $this->fails->inc(['method' => $method]);
            $this->log($e->getMessage());
            $this->log($e->getTraceAsString());
            $result = $e;
        }

        $functionDuration = microtime(true) - $functionStarted;
        $this->durations->observe($functionDuration, ['method' => $method]);
        $this->log(sprintf('Completed in %2.6fs', $functionDuration));

        if (isset($e)) {
            fwrite($conn, "${proto} 500 Internal Server Error\r\n");
            fwrite($conn, "Content-Type: text/plain\r\n");
            fwrite($conn, "Content-Length: " . strlen($e->getMessage()) . "\r\n");
            fwrite($conn, "\r\n");
            fwrite($conn, $e->getMessage());
        } elseif($result instanceof Response) {
            $result->headers->set('content-length', strlen($result->getContent()));
            $result->headers->set('connection', 'close');
            fwrite($conn, (string) $result);
        } else {
            fwrite($conn, "${proto} 200 OK\r\n");
            fwrite($conn, "Content-Type: text/plain\r\n");
            fwrite($conn, "Content-Length: " . strlen($result) . "\r\n");
            fwrite($conn, "\r\n");
            fwrite($conn, $result);
        }
    }

    private function healthz($conn, $proto) : void
    {
        fwrite($conn, "${proto} 200 OK\r\n");
        fwrite($conn, "Content-Type: text/plain\r\n");
        fwrite($conn, "Content-Length: 2\r\n");
        fwrite($conn, "\r\n");
        fwrite($conn, "OK");
    }

    private function metrics($conn, $proto) : void
    {
        $render = new RenderTextFormat();
        $result = $render->render($this->registry->getMetricFamilySamples());

        fwrite($conn, "${proto} 200 OK\r\n");
        fwrite($conn, "Content-Type: text/plain\r\n");
        fwrite($conn, "Content-Length: " . strlen($result) . "\r\n");
        fwrite($conn, "\r\n");
        fwrite($conn, $result);
    }

    public function log(string $message)
    {
        fwrite(STDERR, sprintf(
            "[%s] [%d] %s", date('Y-m-d H:i:s'), getmypid(), $message
        ) . PHP_EOL);
    }
}
