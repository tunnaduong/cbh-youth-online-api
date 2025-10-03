<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Chuyen Bien Hoa API Documentation",
 *      description="This is the API documentation for the Chuyen Bien Hoa project.",
 *      @OA\Contact(
 *          email="admin@chuyenbienhoa.com"
 *      ),
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 */
/**
 * Base controller class for the application.
 *
 * This class provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
