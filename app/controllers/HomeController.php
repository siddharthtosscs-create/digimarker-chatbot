<?php
declare(strict_types=1);

/**
 * HomeController - Handles home page requests
 */
class HomeController
{
  /**
   * Display home page
   */
  public function index(): void
  {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'home.php';
  }
}

