<?php
/**
 * CbRewriter 2.0
 * Use fancy urls like "de_DE/index" instead of "?language=de_DE&page=index"
 *
 * Author: Johannes Wüller
 * Created On: 24.02.2010
 *
 * Extracts parameters from a given request string by applying the provided routes.
 * The routes are applied in the defined order. The first matching route is used.
 * If no route matches, the fallback params (defined by setFallback()) are
 * returned. If the request is empty, the default params (defined by setRoot())
 * are returned.
 *
 * Routes are defined by regular expressions. Named groups can (and
 * should) be used (you still get numerical indexes when using named groups)!
 * Named groups are defined like:
 *    (?<name>regexp)
 * Example:
 *    (?<page>[a-z_]+)
 * This match would be accessible via:
 *    $rewrittenParams['page']
 *
 * Usage:
 *    // $_GET before rewriting: Array(
 *    //    [q] => de_DE/index
 *    // )
 *
 *    $rewriter = new Rewriter($_GET['q']);
 *    $rewriter->setRoutes(array(
 *       '/(?<language>[a-z]{2}_[A-Z]{2})\/(?<page>[a-z_]+)/'
 *    ));
 *    $_GET = $rewriter->get();
 *    
 *    // $_GET after rewriting: Array(
 *    //    [0] => de_DE/index
 *    //    [language] => de_DE
 *    //    [1] => de_DE
 *    //    [page] => index
 *    //    [2] => index
 *    // )
 */
class CbRewriter2 {
   private $request        = null;
   private $routes         = array();
   private $root           = array();
   private $fallback       = array();
   private $loggingEnabled = false;
   private $log            = '';
   
   /**
    * Constructs rewriter with a given request string
    */
   public function __construct($request = null) {
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
   
   public function __destruct() {
      if ($this->loggingEnabled) {
         error_log("CbRewriter2:\n".$this->log);
      }
   }
   
   public function setLogging($enabled) {
      $this->loggingEnabled = $enabled;
   }
   
   private function log($message) {
      $this->log .= $message."\n";
   }
   
   /**
    * Sets routes (array of regular expressions with named subpatterns; see
    * http://php.net/preg_match example #4 for details)
    */
   public function setRoutes($routes = array()) {
      $this->routes = $routes;
   }
   
   /**
    * Sets params that apply if the request is empty.
    */
   public function setRoot($root) {
      $this->root = $root;
   }

   /**
    * Sets params that apply if no route matches.
    */
   public function setFallback($fallback) {
      $this->fallback = $fallback;
   }
   
   /**
    * Finds matching route and returns its params.
    */
   public function get() {
      // no rewriting for empty requests
      if (empty($this->request)) {
         $this->log('empty request');
         
         // return default parameters for empty requests
         return $this->root;
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
    */
   public function getRequest() {
      return $this->request;
   }
   
   /**
    * Rewrite request and add resulting parameters to $_GET so that it can be
    * used like in every other request which has no rewriting enabled.
    * Alternatively you could use $rewriter->get() to recieve the resulting
    * parameters without merging.
    */
   public function mergeGet() {
      $_GET = array_merge($_GET, $this->get());
   }
   
   /**
    * Returns wether we are in the index.php file.
    */
   public static function isIndex() {
      return basename($_SERVER['SCRIPT_NAME']) == 'index.php';
   }
}
?>
