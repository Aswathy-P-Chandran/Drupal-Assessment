<?php

namespace Drupal\dataset_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling Dropzone file uploads.
 */
class DropzoneUploadController extends ControllerBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new DropzoneUploadController.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * Handle file upload from Dropzone.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with file information or error.
   */
  public function upload(Request $request) {
    $files = $request->files->get('file');
    
    if (!$files) {
      return new JsonResponse(['error' => 'No file uploaded'], 400);
    }
    
    // Validate file type
    $allowed_extensions = ['xlsx', 'csv'];
    $extension = strtolower(pathinfo($files->getClientOriginalName(), PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
      return new JsonResponse([
        'error' => 'Invalid file type. Only XLSX and CSV files are allowed.'
      ], 400);
    }
    
    // Validate file size (10MB limit)
    $max_size = 10 * 1024 * 1024; // 10MB in bytes
    if ($files->getSize() > $max_size) {
      return new JsonResponse([
        'error' => 'File is too large. Maximum size is 10MB.'
      ], 400);
    }
    
    // Create destination directory
    $destination = 'public://dataset_uploads/';
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
    
    try {
      // Generate unique filename
      $filename = $this->generateUniqueFilename($files->getClientOriginalName(), $destination);
      
      // Save file
      $file = file_save_data(
        file_get_contents($files->getPathname()),
        $destination . $filename,
        FileSystemInterface::EXISTS_RENAME
      );
      
      if ($file) {
        // Set file as permanent
        $file->setPermanent();
        $file->save();
        
        return new JsonResponse([
          'fid' => $file->id(),
          'filename' => $file->getFilename(),
          'original_name' => $files->getClientOriginalName(),
          'uri' => $file->getFileUri(),
          'url' => $file->createFileUrl(),
          'size' => $file->getSize(),
          'mime_type' => $file->getMimeType(),
        ]);
      }
    } catch (\Exception $e) {
      \Drupal::logger('dataset_publisher')->error('File upload error: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    
    return new JsonResponse(['error' => 'Failed to save file'], 500);
  }
  
  /**
   * Handle file deletion.
   *
   * @param int $fid
   *   The file ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function delete($fid) {
    try {
      $file = File::load($fid);
      
      if ($file) {
        // Check if user has permission to delete this file
        if ($this->currentUser()->hasPermission('delete any file')) {
          $file->delete();
          return new JsonResponse(['success' => true]);
        } else {
          return new JsonResponse(['error' => 'Access denied'], 403);
        }
      }
    } catch (\Exception $e) {
      \Drupal::logger('dataset_publisher')->error('File deletion error: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    
    return new JsonResponse(['error' => 'File not found'], 404);
  }

  /**
   * Generate a unique filename.
   *
   * @param string $original_name
   *   The original filename.
   * @param string $destination
   *   The destination directory.
   *
   * @return string
   *   The unique filename.
   */
  private function generateUniqueFilename($original_name, $destination) {
    $info = pathinfo($original_name);
    $basename = $info['filename'];
    $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
    
    $filename = $basename . $extension;
    $counter = 1;
    
    while (file_exists($destination . $filename)) {
      $filename = $basename . '_' . $counter . $extension;
      $counter++;
    }
    
    return $filename;
  }

}