<?php
/**
 * @brief
 * Use fancy urls like "de_DE/index" instead of "?language=de_DE&page=index"
 *
 * Author: Johannes Wüller
 * Created On: 24.02.2010
 *
 * Extracts parameters from a given request string by applying the provided
 * routes. The routes are applied in the defined order. The first matching route
 * is used. If no route matches or the request is empty, the fallback params
 * (defined by setFallback()) are returned.
 *
 * Routes are defined via regular expressions. Named groups can (and should) be
 * used (you still get numerical indexes when using named groups)!
 *
 * Named groups are defined like:
 *    (?<name>regexp)
 * Example:
 *    (?<page>[a-z_]+)
 * This match would be accessible via:
 *    $rewrittenParams['page']
 *
 * Usage (simple example):
 *    CbRewriter2::create(array(
 *       '/(?<language>\w+)\/(?<page>\w+)/'
 *    ))->setFallback(array(
 *       'language' => 'en_EN',
 *       'page'     => 'index'
 *    ))->mergeGet();
 */
class CbRewriter2 {
   private $request        = null;
   private $routes         = array();
   private $fallback       = array();
   private $loggingEnabled = false;
   private $log            = '';

   /**
    * Calls the constructor to allow oneliners to be used (since PHP does not
    * allow using new instances immediately without storing them in a variable).
    * All parameters get directly passed to the constructor. The arguments can
    * be used exactly the same way.
    *
    * @return CbRewriter2 instance
    */
   public static function create(/* constructor arguments go here */) {
      // PHP needs this function to be not used as an argument to another
      // function (another PHP WTF).
      $args = func_get_args();

      // This is not properly documented at the time of writing, but i think you
      // get what happens here (ReflectionClass is a built-in PHP class).
      $class = new ReflectionClass(__CLASS__);
      $instance = $class->newInstanceArgs($args);

      return $instance;
   }
   
   /**
    * Sets (optional) routes (array of regular expressions with named
    * subpatterns; see http://php.net/preg_match example #4 for details) and an
    * optional request string to be rewritten.
    *
    * @param routes (Optional) list of regular expressions
    * @param request (Optional) string to be analyzed
    */
   public function __construct($routes = array(), $request = null) {
      $this->setLogging(isset($_GET['cb-debug']));
      $this->setRoutes($routes);
      $this->setRequest($request !== null ? $request : self::getDefaultRequest());
   }

   /**
    * Writes the internal log to the php error log if logging is enabled.
    */
   public function __destruct() {
      if ($this->loggingEnabled) {
         error_log(sprintf("CbRewriter2:\n<pre>%s</pre>", $this->log));
      }
   }

   /**
    * Builds the currently requested url for use with the rewriter.
    *
    * @return request string
    */
   public static function getDefaultRequest() {
      $request = $_SERVER['REQUEST_URI'];

      // remove relative document root
      $docroot = dirname($_SERVER['SCRIPT_NAME']);
      $request = preg_replace('/^'.preg_quote($docroot, '/').'\//', '', $request);

      // remove get params
      $request = preg_replace('/^([^\?&]+).*$/', '$1', $request);

      return $request;
   }

   /**
    * Enables or disables internal logging (this is very useful for debugging
    * your routes, obviously). Logging is disabled by default.
    *
    * @param enabled Wether logging should be enabled
    * @return Self
    */
   public function setLogging($enabled) {
      $this->loggingEnabled = $enabled;

      return $this;
   }

   /**
    * Provides logging for internal purposes. Parameters are equal to the ones
    * that printf recieves.
    *
    * @return Self
    */
   private function log() {
      if ($this->loggingEnabled) {
         $args = func_get_args();
         $this->log .= call_user_func_array('sprintf', $args)."\n";
      }

      return $this;
   }

   /**
    * Sets the routes used to analyze requests.
    *
    * @param routes List of regular expressions
    * @return Self
    */
   public function setRoutes($routes) {
      $this->routes = $routes;

      return $this;
   }

   /**
    * Returns the currently used routes.
    *
    * @return List of regular expressions
    */
   public function getRoutes() {
      return $this->routes;
   }

   /**
    * Sets params that apply if no route matches.
    *
    * @param fallback List of params that are returned by get() if none of the
    *    specified routes match
    * @return Self
    */
   public function setFallback($fallback) {
      $this->fallback = $fallback;

      return $this;
   }

   /**
    * Returns the currently used fallback parameters.
    *
    * @return List of params
    */
   public function getFallback() {
      return $this->fallback;
   }

   /**
    * Sets the request string.
    *
    * @param request String to be parsed using the routes
    * @return Self
    */
   public function setRequest($request) {
      // remove leading and trailing spaces and slashes
      $request = trim($request);
      $request = trim($request, '/');
      
      $this->request = $request;

      return $this;
   }
   
   /**
    * Returns the request the rewriter was initialized with.
    *
    * @return Request that is analyzed
    */
   public function getRequest() {
      return $this->request;
   }

   /**
    * Finds matching route and returns its params (this is the main
    * functionality of the rewriter).
    *
    * @param merge_fallback whether to add fallbacks for parameters that weren't
    *    rewritten, even if a match was found.
    * @return Regular expression matches of the matching route, fallback
    *    otherwise (if no route matched or the request was empty)
    */
   public function get($merge_fallback = false) {
      $request = $this->getRequest();

      // no rewriting for empty requests
      if (empty($request)) {
         $this->log('empty request');

         // return default parameters for empty requests
         return $this->fallback;
      }

      // actual rewriting
      $this->log('request: %s', $request);
      
      foreach ($this->getRoutes() as $route) {
         $this->log('test: %s', $route);
         
         if (preg_match($route, $request, $matches)) {
            if ($merge_fallback) {
               foreach ($this->getFallback() as $param => $val) {
                  if (!array_key_exists($param, $matches)) $matches[$param] = $val;
               }
            }
            $this->log('match found; result: %s', print_r($matches, true));

            // abort after the first matching route
            return $matches;
         }
      }

      // return default parameters for non-matching requests
      $this->log('no match found');
      return $this->getFallback();
   }
   
   /**
    * Rewrite request and add resulting parameters to $_GET so that it can be
    * used like in every other request which has no rewriting enabled.
    * Alternatively you could use $rewriter->get() to recieve the resulting
    * parameters without merging.
    *
    * @param override whether to override explicitly (with '?param=val') set GET
    *                 parameters with rewritten ones or not.
    * @param merge_fallback whether to add fallbacks for parameters that weren't
    *                 rewritten, even if a match was found.
    * @return Self
    */
   public function mergeGet($override = true, $merge_fallback = false) {
      if ($override) {
         $_GET = array_merge($_GET, $this->get($merge_fallback));
      } else {
         $_GET = array_merge($this->get($merge_fallback), $_GET);
      }
      return $this;
   }
   
   /**
    * Determines wether we are in the index.php file. This method is a helper
    * that can be used to prevent rewriting ajax entry points, etc.
    *
    * @return Wether we are in index.php
    */
   public static function isIndex() {
      return basename($_SERVER['SCRIPT_NAME']) === 'index.php';
   }
}