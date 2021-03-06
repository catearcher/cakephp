<?php
/**
 * CakePHP(tm) :  Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://www.cakefoundation.org/projects/info/cakephp CakePHP(tm) Project
 * @since         2.2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Log\Engine;

use Cake\Console\ConsoleOutput;
use InvalidArgumentException;

/**
 * Console logging. Writes logs to console output.
 */
class ConsoleLog extends BaseLog
{

    /**
     * Default config for this class
     *
     * @var array
     */
    protected $_defaultConfig = [
        'stream' => 'php://stderr',
        'levels' => null,
        'scopes' => [],
        'outputAs' => 'see constructor'
    ];

    /**
     * Output stream
     *
     * @var \Cake\Console\ConsoleOutput
     */
    protected $_output = null;

    /**
     * Constructs a new Console Logger.
     *
     * Config
     *
     * - `levels` string or array, levels the engine is interested in
     * - `scopes` string or array, scopes the engine is interested in
     * - `stream` the path to save logs on.
     * - `outputAs` integer or ConsoleOutput::[RAW|PLAIN|COLOR]
     *
     * @param array $config Options for the FileLog, see above.
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = [])
    {
        if ((DS === '\\' && !(bool)env('ANSICON')) ||
            (function_exists('posix_isatty') && !posix_isatty($this->_output))
        ) {
            $this->_defaultConfig['outputAs'] = ConsoleOutput::PLAIN;
        } else {
            $this->_defaultConfig['outputAs'] = ConsoleOutput::COLOR;
        }

        parent::__construct($config);

        $config = $this->_config;
        if ($config['stream'] instanceof ConsoleOutput) {
            $this->_output = $config['stream'];
        } elseif (is_string($config['stream'])) {
            $this->_output = new ConsoleOutput($config['stream']);
        } else {
            throw new InvalidArgumentException('`stream` not a ConsoleOutput nor string');
        }
        $this->_output->outputAs($config['outputAs']);
    }

    /**
     * Implements writing to console.
     *
     * @param string $level The severity level of log you are making.
     * @param string $message The message you want to log.
     * @param array $context Additional information about the logged message
     * @return bool success of write.
     */
    public function log($level, $message, array $context = [])
    {
        $message = $this->_format($message, $context);
        $output = date('Y-m-d H:i:s') . ' ' . ucfirst($level) . ': ' . $message;
        return $this->_output->write(sprintf('<%s>%s</%s>', $level, $output, $level));
    }
}
