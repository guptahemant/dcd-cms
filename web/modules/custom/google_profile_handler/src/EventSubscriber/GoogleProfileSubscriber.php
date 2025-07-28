<?php

namespace Drupal\google_profile_handler\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\social_auth\Event\UserEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to Social Auth events for Google users.
 */
class GoogleProfileSubscriber implements EventSubscriberInterface {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * GoogleProfileSubscriber constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
     $events[SocialAuthEvents::USER_CREATED] = ['onUserCreated'];
      return $events;
  }

  /**
   * Processes Google user data when a new user is created.
   *
   * @param \Drupal\social_auth\Event\UserEvent $event
   *   The Social Auth user event.
   */
  public function onUserCreated(UserEvent $event) {
    // Only act on Google authentication.
    if ($event->getPluginId() !== 'social_auth_google') {
      return;
    }

    // Get the user created.
    $user = $event->getUser();
    $socialAuthUser = $event->getSocialAuthUser();

    // Log with exact format requested
    $timestamp = gmdate('Y-m-d H:i:s');

    try {   
      // Save the profile data using the social auth user object
      $this->saveProfileDataToUser($user, $socialAuthUser, $timestamp);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('google_profile_handler')->error(
        'Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): @timestamp' . PHP_EOL . 
        'Error processing Google profile: @message', [
          '@timestamp' => $timestamp,
          '@message' => $e->getMessage(),
          '@trace' => $e->getTraceAsString(),
        ]
      );
    }
  }

  /**
   * Save Google profile data to user profile.
   */
  protected function saveProfileDataToUser($user, $userInfo, $timestamp) {
    // Load or create profile entity for the user
    $profile = $this->getOrCreateUserProfile($user, $timestamp);
    
    if (!$profile) {
      $this->loggerFactory->get('google_profile_handler')->error(
        'Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): @timestamp' . PHP_EOL . 
        'Could not create or load profile for user @uid', [
          '@timestamp' => $timestamp,
          '@uid' => $user->id(),
        ]
      );
      return;
    }

    // Save profile picture if field exists
    if ($profile->hasField('field_photo_user') && $userInfo->getPictureUrl()) {
      $this->saveProfilePicture($profile, $userInfo->getPictureUrl(), $timestamp);
    }

    // Try to fetch phone number from Google
    $phoneNumber = $this->fetchPhoneNumberFromGoogle($userInfo, $timestamp);

    // Map Google profile fields to Drupal profile fields
    $mappings = [
      ['FirstName', 'field_first_name'],
      ['LastName', 'field_last_name'],
      ['Name', 'field_full_name'],
      ['Email', 'field_email'],
      ['ProviderUserID', 'field_google_id'],
    ];

    // Add phone number to profile if available
    if ($phoneNumber && $profile->hasField('field_phone')) {
      $profile->set('field_phone', $phoneNumber);
    }
    
    foreach ($mappings as $mapping) {
      [$getter, $drupal_field] = $mapping;
      $method = 'get' . $getter;
      
      // Only proceed if the method exists on the userInfo object
      if (method_exists($userInfo, $method) && $profile->hasField($drupal_field)) {
        $value = $userInfo->$method();
        
        // Skip if value is empty
        if (empty($value)) {
          continue;
        }
        
        $profile->set($drupal_field, $value);
      }
    }
    
    // Save the profile
    $profile->save();
  }

  /**
   * Get or create a profile for the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param string $timestamp
   *   The current timestamp.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The profile entity or null if it couldn't be created.
   */
  protected function getOrCreateUserProfile($user, $timestamp) {
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    
    // Try to load existing profile
    $profiles = $profile_storage->loadByProperties([
      'uid' => $user->id(),
      'type' => 'profile',
      'status' => TRUE,
    ]);
    
    if (!empty($profiles)) {
      // Return the first active profile
      return reset($profiles);
    }
    
    // Create new profile if none exists
    try {
      $profile = $profile_storage->create([
        'type' => 'profile',
        'uid' => $user->id(),
        'status' => TRUE,
      ]);
      
      return $profile;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('google_profile_handler')->error(
        'Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): @timestamp' . PHP_EOL . 
        'Failed to create profile for user @uid: @message', [
          '@timestamp' => $timestamp,
          '@uid' => $user->id(),
          '@message' => $e->getMessage(),
        ]
      );
      return NULL;
    }
  }

  /**
   * Fetch phone number from Google using People API.
   *
   * @param \Drupal\social_auth\User\SocialAuthUser $userInfo
   *   The social auth user object.
   * @param string $timestamp
   *   The current timestamp.
   *
   * @return string|null
   *   The phone number or null if not available.
   */
  protected function fetchPhoneNumberFromGoogle($userInfo, $timestamp) {
      // If not in additional data, try Google People API
      $access_token = $userInfo->getToken();
      if (empty($access_token)) {
        return null;
      }

      $client = \Drupal::httpClient();
      $response = $client->request('GET', 'https://people.googleapis.com/v1/people/me?personFields=phoneNumbers', [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      // Extract phone number from API response
      if (!empty($data['phoneNumbers'])) {
        return $data['phoneNumbers'][0]['value'];
      }

      return null;
  }

  /**
   * Save Google profile picture to profile entity.
   */
  protected function saveProfilePicture($profile, $picture_url, $timestamp) {
    try {      
      $file_data = file_get_contents($picture_url);
      if ($file_data) {
        // Get the original file extension from the URL or detect from content
        $path_info = pathinfo($picture_url);
        $extension = isset($path_info['extension']) ? $path_info['extension'] : 'jpg';
        
        // If no extension found in URL, try to detect from file content
        if (empty($extension) || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
          $finfo = new \finfo(FILEINFO_MIME_TYPE);
          $mime_type = $finfo->buffer($file_data);
          
          switch ($mime_type) {
            case 'image/jpeg':
              $extension = 'jpg';
              break;
            case 'image/png':
              $extension = 'png';
              break;
            case 'image/gif':
              $extension = 'gif';
              break;
            case 'image/webp':
              $extension = 'webp';
              break;
            default:
              $extension = 'jpg'; // fallback
          }
        }
        
        $directory = 'public://user_pictures';
        \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $destination = $directory . '/' . 'google_' . $profile->getOwnerId() . '.' . $extension;

        // Save the file to public files directory
        $file = \Drupal::service('file.repository')->writeData($file_data, $destination, FileSystemInterface::EXISTS_RENAME);

        if ($file) {
          // Make the file permanent
          $file->setPermanent();
          $file->save();
                    
          // Set the file directly on the profile field
          $profile->set('field_photo_user', [
            'target_id' => $file->id(),
            'alt' => 'Google Profile Picture',
          ]);
        }
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('google_profile_handler')->error(
        'Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): @timestamp' . PHP_EOL . 
        'Failed to save profile picture: @message', [
          '@timestamp' => $timestamp,
          '@message' => $e->getMessage(),
        ]
      );
    }
  }
}
