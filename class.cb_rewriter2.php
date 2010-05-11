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
 * Usage:
 *    $rewriter = new CbRewriter2(array(
 *       '/(?<language>\w+)\/(?<page>\w+)/'
 *    ));
 *    $rewriter->setFallback(array(
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
    * Sets (optional) routes (array of regular expressions with named
    * subpatterns; see http://php.net/preg_match example #4 for details) and an
    * optional request string to be rewritten.
    */
   public function __construct($routes = array(), $request = null) {
      $this->routes = $routes;
      
      if ($request === null) {
         $docroot = dirname($_SERVER['SCRIPT_NAME']);
         
         // remove docroot
         $request = preg_replace('/^'.preg_quote($docroot, '/').'\//', '', $_SERVER['REQUEST_URI']);
         
         // remove get params
         $request = preg_replace('/^(.*)(\?|&).*$/', '$1', $request);
      }
      
      // remove leading and trailing spaces and slashes
      $request = trim($request);
      $request = preg_replace('/^\/+/', '', $request);
      $request = preg_replace('/\/+$/', '', $request);
      
      $this->request = $request;
   }

   /**
    * Writes the internal log to the php error log if logging is enabled.
    */
   public function __destruct() {
      if ($this->loggingEnabled) {
         error_log("CbRewriter2:\n".$this->log);
      }
   }

   /**
    * Enables or disables internal logging.
    *
    * @param enabled Wether logging should be enabled.
    */
   public function setLogging($enabled) {
      $this->loggingEnabled = $enabled;

      return $this;
   }

   /**
    * Provides logging for internal purposes.
    *
    * @param message What to log
    */
   private function log($message) {
      $this->log .= $message."\n";
   }

   /**
    * Sets params that apply if no route matches.
    */
   public function setFallback($fallback) {
      $this->fallback = $fallback;

      return $this;
   }
   
   /**
    * Finds matching route and returns its params.
    */
   public function get() {
      // no rewriting for empty requests
      if (empty($this->request)) {
         $this->log('empty request');
         
         // return default parameters for empty requests
         return $this->fallback;
      }
      
      // actual rewriting
      $this->log('request: '.$this->request);
      foreach ($this->routes as $route) {
         $this->log('test: '.$route);
         if (preg_match($route, $this->request, $matches)) {
            $this->log('match found');
            
            // abort after the first matching route
            return $matches;
         }
      }
      
      // return default parameters for non-matching requests
      $this->log('no match found');
      return $this->fallback;
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
    * Rewrite request and add resulting parameters to $_GET so that it can be
    * used like in every other request which has no rewriting enabled.
    * Alternatively you could use $rewriter->get() to recieve the resulting
    * parameters without merging.
    *
    * @return Self
    */
   public function mergeGet() {
      $_GET = array_merge($_GET, $this->get());

      return $this;
   }
   
   /**
    * Returns wether we are in the index.php file.
    */
   public static function isIndex() {
      return basename($_SERVER['SCRIPT_NAME']) == 'index.php';
   }
}
?>
