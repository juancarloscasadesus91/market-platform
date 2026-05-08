<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class TunnelController extends Controller
{
    private $pidFile = '/tmp/tunnel_run.pid';
    private $logFile = '/tmp/tunnel_run.log';
    private $offsetFile = '/tmp/tunnel_run.offset';
    private $scriptPath = '/home/jhony/Documentos/TOS/auth/schwab/callback/run.sh';

    public function start()
    {
        // Check if already running
        if ($this->isRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Tunnel is already running'
            ]);
        }

        // Clear previous log and offset
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        if (file_exists($this->offsetFile)) {
            unlink($this->offsetFile);
        }
        file_put_contents($this->offsetFile, '0');

        // Start the tunnel script in background
        $command = "bash {$this->scriptPath} > {$this->logFile} 2>&1 & echo $!";
        $pid = shell_exec($command);
        
        if ($pid) {
            file_put_contents($this->pidFile, trim($pid));
            
            return response()->json([
                'success' => true,
                'message' => 'Tunnel started successfully',
                'pid' => trim($pid)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to start tunnel'
        ]);
    }

    public function stop()
    {
        if (!$this->isRunning()) {
            return response()->json([
                'success' => true,
                'message' => 'Tunnel is not running'
            ]);
        }

        $pid = file_get_contents($this->pidFile);
        
        // Kill the process and all its children
        shell_exec("pkill -P " . trim($pid));
        shell_exec("kill " . trim($pid) . " 2>/dev/null");
        
        unlink($this->pidFile);

        return response()->json([
            'success' => true,
            'message' => 'Tunnel stopped successfully'
        ]);
    }

    public function output()
    {
        $output = [];
        $running = $this->isRunning();
        $offset = 0;

        // Get current offset
        if (file_exists($this->offsetFile)) {
            $offset = (int) file_get_contents($this->offsetFile);
        }

        if (file_exists($this->logFile)) {
            $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
            
            // Get only new lines since last offset
            $newLines = array_slice($lines, $offset);
            
            // Update offset
            $newOffset = count($lines);
            file_put_contents($this->offsetFile, $newOffset);
            
            // Strip ANSI color codes
            $output = array_map(function($line) {
                return preg_replace('/\x1b\[[0-9;]*m/', '', $line);
            }, $newLines);
        }

        return response()->json([
            'running' => $running,
            'output' => $output
        ]);
    }

    public function status()
    {
        return response()->json([
            'running' => $this->isRunning(),
            'pid' => $this->isRunning() ? file_get_contents($this->pidFile) : null
        ]);
    }

    private function isRunning()
    {
        if (!file_exists($this->pidFile)) {
            return false;
        }

        $pid = trim(file_get_contents($this->pidFile));
        
        // Check if process is still running
        $result = shell_exec("ps -p $pid -o pid= 2>/dev/null");
        
        if (empty(trim($result))) {
            unlink($this->pidFile);
            return false;
        }

        return true;
    }
}
