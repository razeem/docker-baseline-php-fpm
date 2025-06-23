<?php

declare(strict_types=1);

namespace Razeem\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * This class implements a Composer plugin that copies docker configuration files to the project root.
 * @psalm-suppress MissingConstructor
 */
class CopyDistPlugin implements PluginInterface, EventSubscriberInterface {

  /**
   * @var Composer\IO\IOInterface
   */
  private $io;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->io = $io;
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io): void {}

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io): void {}

  public static function getSubscribedEvents() {
    return [
      ScriptEvents::POST_INSTALL_CMD => ['copyDist', 10],
      ScriptEvents::POST_UPDATE_CMD => ['copyDist', 10],
    ];
  }

  /**
   * Copies the dist folder from the plugin to the main project root.
   * If a project-code.txt file exists in the target or project root, its contents
   * are used to replace all occurrences of 'project_name' in docker-compose.yml files.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event object.
   */
  public function copyDist(Event $event) {
    $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
    $pluginDir = dirname(__DIR__); // Adjust if needed
    $distSource = $pluginDir . '/dist';
    $projectRoot = dirname($vendorDir);
    $distTarget = $projectRoot . '/dist';

    if (!is_dir($distSource)) {
      $event->getIO()->writeError('<error>No dist folder found in plugin.</error>');
      return;
    }

    // Read project code from project-code.txt
    $projectCodeFile = $distTarget . '/project-code.txt';
    if (!file_exists($projectCodeFile)) {
      $projectCodeFile = $projectRoot . '/project-code.txt';
    }
    $projectCode = strtolower(
      file_exists($projectCodeFile)
        ? trim(file_get_contents($projectCodeFile))
        : substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3)
    ) . '_docker_local';
    // $projectCode = strtolower($projectCode);

    $this->recurseCopyWithReplace($distSource, $distTarget, $projectCode);

    $event->getIO()->write('<info>dist folder copied to project root.</info>');
  }

  /**
   * Recursively copies files and directories from source to destination.
   * If a docker-compose.yml file is found, replaces 'project_name' with the given project code.
   *
   * @param string $src
   *   Source directory path.
   * @param string $dst
   *   Destination directory path.
   * @param string $projectCode
   *   The project code to replace 'project_name' with in docker-compose.yml.
   */
  private function recurseCopyWithReplace($src, $dst, $projectCode) {
    $dir = opendir($src);
    @mkdir($dst, 0777, TRUE);
    while (FALSE !== ($file = readdir($dir))) {
      if (($file != '.') && ($file != '..')) {
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
          $this->recurseCopyWithReplace($srcPath, $dstPath, $projectCode);
        }
        else {
          // If docker-compose.yml, replace project_name with project code
          if ($file === 'docker-compose.yml') {
            $content = file_get_contents($srcPath);
            $content = str_replace('project_name', $projectCode, $content);
            file_put_contents($dstPath, $content);
          }
          else {
            copy($srcPath, $dstPath);
          }
        }
      }
    }
    closedir($dir);
  }

}
