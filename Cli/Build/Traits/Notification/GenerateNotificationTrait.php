<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for generating Notification components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Notification;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait GenerateNotificationTrait
{
    public function emailNotification(string $email_notification, string $module): array
    {
        return $this->customNotification($email_notification, 'email', $module);
    }

    public function customNotification(string $custom_notification, string $type, string $module): array
    {
        $default_template_path = Paths::cliPath('Build/Templates/');
        $custom_template_path = Paths::storagePath('build/templates/');
        $template = ucfirst($type) . 'NotificationTemplate.php.stub';
        $param = "'" . strtolower($custom_notification) . "'";

        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);
        $namespace = 'App\\' . $module_name;

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $custom_template = $custom_template_path . $template;
        $default_template = $default_template_path . $template;

        $custom_notification_name = ucfirst($custom_notification) . ucfirst($type) . 'Notification';
        $custom_notification_dir = $directory . '/Notifications/' . ucfirst($type) . '/';

        FileSystem::mkdir($custom_notification_dir);

        $file = $custom_notification_dir . $custom_notification_name . '.php';

        if (FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $custom_notification_name . ' ' . $type . ' notification build not successful, ' . $custom_notification_name . ' already exist.',
            ];
        }

        $templatefile = FileSystem::exists($custom_template) ? $custom_template : $default_template;

        if (FileSystem::exists($templatefile)) {
            if (strpos(FileSystem::get($templatefile), '{notificationName}') === false) {
                return [
                    'status' => false,
                    'message' => ucfirst($type) . ' notification template file not found.',
                ];
            }

            $newcontent = str_replace(['{namespace}', '{notificationName}'], [$namespace, $custom_notification_name], FileSystem::get($templatefile));

            $generated = FileSystem::put($file, $newcontent);

            if ($generated) {
                return [
                    'status' => true,
                    'message' => $custom_notification_name . ' generated successfully.',
                ];
            }

            return [
                'status' => false,
                'message' => $custom_notification_name . ' could not be generated.',
            ];
        }

        return [
            'status' => false,
            'message' => 'The template file ' . $templatefile . ' does not exist.',
        ];
    }
}
