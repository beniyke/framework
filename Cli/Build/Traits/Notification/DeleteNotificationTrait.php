<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for deleting Notification components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Build\Traits\Notification;

use Helpers\File\FileSystem;
use Helpers\File\Paths;

trait DeleteNotificationTrait
{
    public function emailNotification(string $email_notification, string $module): array
    {
        return $this->customNotification($email_notification, 'email', $module);
    }

    public function customNotification(string $custom_notification, string $type, string $module): array
    {
        $module_name = ucfirst($module);
        $directory = Paths::appSourcePath($module_name);

        if (! FileSystem::exists($directory)) {
            return [
                'status' => false,
                'message' => 'The module ' . $module_name . ' does not exist kindly create ' . $module_name . ' module.',
            ];
        }

        $custom_notification_name = ucfirst($custom_notification) . ucfirst($type) . 'Notification';
        $file = $directory . '/Notifications/' . ucfirst($type) . '/' . $custom_notification_name . '.php';

        if (! FileSystem::exists($file)) {
            return [
                'status' => false,
                'message' => $custom_notification_name . ' file not found.',
            ];
        }

        $deleted = FileSystem::delete($file);

        if ($deleted) {
            return [
                'status' => true,
                'message' => $custom_notification_name . ' deleted successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => $custom_notification_name . ' could not be deleted.',
        ];
    }
}
