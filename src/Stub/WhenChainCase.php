<?php
namespace ShortifyPunit\Stub;
use ShortifyPunit\Mock\MockClassOnTheFly;
use ShortifyPunit\Enums\MockAction;
use ShortifyPunit\Exceptions\ExceptionFactory;
use ShortifyPunit\ShortifyPunit;

/**
 * Class WhenChainCase
 * @package ShortifyPunit
 * @desc When Case, is used to set up mocking response using specific call arguments
 *       and return action (throw exception, return value, ..)
 */
class WhenChainCase
{
    use ExceptionFactory;

    private $methods = [];
    private $mockClass;

    public function __construct($class)
    {
        $this->mockClass = $class;
    }

    public function __call($method, $args)
    {
        // add to method list if not an action
        if ( ! in_array($method, array(MockAction::RETURNS, MockAction::THROWS)))
        {
            array_unshift($this->methods, array($method => $args));
            return $this;
        }

        if ( ! isset($args[0])) {
            throw static::generateException("`{$method}` must be called with an argument !");
        }

        // if an action, create chain array of return values
        $this->createChainArrayOfReturnValues($method, $args[0]);

        return $this;
    }

    /**
     * Creating a chain array of return values
     *
     * @param $action
     * @param $response
     */
    private function createChainArrayOfReturnValues($action, $response)
    {
        // Pop first method
        $methods = $this->methods;
        $firstMethod = array_pop($methods);

        $lastValue = $response;

        foreach($methods as $currentMethod)
        {
            $fakeClass = new MockClassOnTheFly();

            // extracting methods before the current method into an array
            $chainedMethodsBefore = $this->extractChainedMethodsBefore(array_reverse($this->methods), $currentMethod);

            // adding to the ShortifyPunit chained method response
            $this->addChainedMethodResponse($chainedMethodsBefore, $currentMethod, $action, $lastValue);

            $currentMethodName = key($currentMethod);

            // closure for MockOnTheFly chained methods
            $fakeClass->$currentMethodName = function() use ($chainedMethodsBefore, $currentMethod) {
                return ShortifyPunit::__create_chain_response($chainedMethodsBefore, $currentMethod, func_get_args());
            };

            $lastValue = $fakeClass;

            // except from the last method all other chained method `returns` a calls so set the action for the next loop
            $action = MockAction::RETURNS;
        }

        $whenCase = new WhenCase(get_class($this->mockClass), $this->mockClass->mockInstanceId, key($firstMethod));
        $whenCase->setMethod(current($firstMethod), $action, $lastValue);
    }

    /**
     * Adding chained method responses into ShortifyPunit::ReturnValues
     *
     * @param $chainedMethodsBefore
     * @param $currentMethod
     * @param $action
     * @param $lastValue
     */
    private function addChainedMethodResponse($chainedMethodsBefore, $currentMethod, $action, $lastValue)
    {
        $response = [];
        $rResponse = &$response;

        $currentMethodName = key($currentMethod);

        foreach ($chainedMethodsBefore as $chainedMethod)
        {
            $chainedMethodName = key($chainedMethod);
            $chainedMethodArgs = $chainedMethod[$chainedMethodName];

            $key = $chainedMethodName.serialize($chainedMethodArgs);
            $rResponse[$key] = [];
            $rResponse = &$rResponse[$key];
        }

        $rResponse[$currentMethodName.serialize(current($currentMethod))] = ['response' => ['action' => $action, 'value' => $lastValue]];

        ShortifyPunit::addChainedResponse($response);
    }

    /**
     * Extracting chained methods before current method into an array
     *
     * @param $methods
     * @param $currentMethod
     * @return array
     */
    private function extractChainedMethodsBefore($methods, $currentMethod)
    {
        $reachedMethod = false;
        $chainedMethodsBefore = [];
        $currentMethodName = key($currentMethod);

        foreach ($methods as $method)
        {
            $methodName = key($method);

            if ($methodName == $currentMethodName) {
                $reachedMethod = true;
            }

            if ($reachedMethod) {
                continue;
            }

            $chainedMethodsBefore[] = $method;
        }

        return $chainedMethodsBefore;
    }
}
