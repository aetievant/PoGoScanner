<?php

class PoGoScannerException extends Exception
{
    /**
     * This method acts like an error handler, if dev mode is on, display the error else use a better silent way
     */
    public function displayMessage()
    {
        header('HTTP/1.1 500 Internal Server Error');
        if (_MODE_DEV_) {
            // Display error message
            echo '<style>
				#psException{font-family: Verdana; font-size: 14px}
				#psException h2{color: #F20000}
				#psException p{padding-left: 20px}
				#psException ul li{margin-bottom: 10px}
				#psException a{font-size: 12px; color: #000000}
				#psException .psTrace, #psException .psArgs{display: none}
				#psException pre{border: 1px solid #236B04; background-color: #EAFEE1; padding: 5px; font-family: Courier; width: 99%; overflow-x: auto; margin-bottom: 30px;}
				#psException .psArgs pre{background-color: #F1FDFE;}
				#psException pre .selected{color: #F20000; font-weight: bold;}
			</style>';
            echo '<div id="psException">';
            echo '<h2>['.get_class($this).']</h2>';
            echo $this->getExtendedMessage();

            $this->displayFileDebug($this->getFile(), $this->getLine());

            // Display debug backtrace
            echo '<ul>';
            foreach ($this->getTrace() as $id => $trace) {
                $relative_file = (isset($trace['file'])) ? ltrim(str_replace(array(_ROOT_DIR_, '\\'), array('', '/'), $trace['file']), '/') : '';
                $current_line = (isset($trace['line'])) ? $trace['line'] : '';

                echo '<li>';
                echo '<b>'.((isset($trace['class'])) ? $trace['class'] : '').((isset($trace['type'])) ? $trace['type'] : '').$trace['function'].'</b>';
                echo ' - <a style="font-size: 12px; color: #000000; cursor:pointer; color: blue;" onclick="document.getElementById(\'psTrace_'.$id.'\').style.display = (document.getElementById(\'psTrace_'.$id.'\').style.display != \'block\') ? \'block\' : \'none\'; return false">[line '.$current_line.' - '.$relative_file.']</a>';

                if (isset($trace['args']) && count($trace['args'])) {
                    echo ' - <a style="font-size: 12px; color: #000000; cursor:pointer; color: blue;" onclick="document.getElementById(\'psArgs_'.$id.'\').style.display = (document.getElementById(\'psArgs_'.$id.'\').style.display != \'block\') ? \'block\' : \'none\'; return false">['.count($trace['args']).' Arguments]</a>';
                }

                if ($relative_file) {
                    $this->displayFileDebug($trace['file'], $trace['line'], $id);
                }
                if (isset($trace['args']) && count($trace['args'])) {
                    $this->displayArgsDebug($trace['args'], $id);
                }
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        // @todo : Log the error in the disk
//        $this->logError();
        exit;
    }

    /**
     * Display lines around current line
     *
     * @param string $file
     * @param int $line
     * @param string $id
     */
    protected function displayFileDebug($file, $line, $id = null)
    {
        $lines = file($file);
        $offset = $line - 6;
        $total = 11;
        if ($offset < 0) {
            $total += $offset;
            $offset = 0;
        }
        $lines = array_slice($lines, $offset, $total);
        ++$offset;

        echo '<div class="psTrace" id="psTrace_'.$id.'" '.((is_null($id) ? 'style="display: block"' : '')).'><pre>';
        foreach ($lines as $k => $l) {
            $string = ($offset + $k).'. '.htmlspecialchars($l);
            if ($offset + $k == $line) {
                echo '<span class="selected">'.$string.'</span>';
            } else {
                echo $string;
            }
        }
        echo '</pre></div>';
    }

    /**
     * Display arguments list of traced function
     *
     * @param array $args List of arguments
     * @param string $id ID of argument
     */
    protected function displayArgsDebug($args, $id)
    {
        echo '<div class="psArgs" id="psArgs_'.$id.'"><pre>';
        foreach ($args as $arg => $value) {
            echo '<b>Argument ['.Tools::safeOutput($arg)."]</b>\n";
            echo Tools::safeOutput(print_r($value, true));
            echo "\n";
        }
        echo '</pre>';
    }

    /**
     * Return the content of the Exception
     * @return string content of the exception
     */
    protected function getExtendedMessage($html = true)
    {
        $format = '<p><b>%s</b><br /><i>at line </i><b>%d</b><i> in file </i><b>%s</b></p>';
        if (!$html) {
            $format = strip_tags(str_replace('<br />', ' ', $format));
        }

        return sprintf(
            $format,
            $this->getMessage(),
            $this->getLine(),
            ltrim(str_replace(array(_ROOT_DIR_, '\\'), array('', '/'), $this->getFile()), '/')
        );
    }
}
