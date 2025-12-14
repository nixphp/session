<?php

declare(strict_types=1);

namespace Tests\Unit;

use NixPHP\Session\Core\Session;
use Tests\NixPHPTestCase;
use function NixPHP\Session\session;

class SessionTest extends NixPHPTestCase
{

    public function testSessionInternals()
    {
        $session = new Session();
        $session->start(function() {return null;});
        $session->set('foo', 'bar');
        $this->assertSame('bar', $session->get('foo'));
        $session->forget('foo');
        $this->assertNull($session->get('foo'));
    }

    public function testSessionFlashMessage()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->flash('foo', 'bar');
        $this->assertSame('bar', $session->getFlash('foo'));
        $this->assertNull($session->getFlash('foo'));
    }

    public function testSessionHelperFunction()
    {
        $this->assertInstanceOf(Session::class, session());
    }

    public function testGetWithDefaultValue()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $this->assertSame('default', $session->get('nonexistent', 'default'));
    }

    public function testGetFlashWithDefaultValue()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $this->assertSame('default', $session->getFlash('nonexistent', 'default'));
    }

    public function testSetMultipleValues()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('key1', 'value1');
        $session->set('key2', 'value2');
        $session->set('key3', 'value3');

        $this->assertSame('value1', $session->get('key1'));
        $this->assertSame('value2', $session->get('key2'));
        $this->assertSame('value3', $session->get('key3'));
    }

    public function testSetOverwritesExistingValue()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('foo', 'bar');
        $this->assertSame('bar', $session->get('foo'));

        $session->set('foo', 'baz');
        $this->assertSame('baz', $session->get('foo'));
    }

    public function testForgetNonexistentKey()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->forget('nonexistent');
        $this->assertNull($session->get('nonexistent'));
    }

    public function testFlashMultipleMessages()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->flash('success', 'Operation successful');
        $session->flash('error', 'An error occurred');

        $this->assertSame('Operation successful', $session->getFlash('success'));
        $this->assertSame('An error occurred', $session->getFlash('error'));
    }

    public function testFlashMessageOnlyAvailableOnce()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->flash('message', 'Hello');
        $session->getFlash('message');

        $this->assertNull($session->getFlash('message'));
    }

    public function testSetDifferentDataTypes()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('string', 'text');
        $session->set('int', 42);
        $session->set('float', 3.14);
        $session->set('bool', true);
        $session->set('array', ['a', 'b', 'c']);

        $this->assertSame('text', $session->get('string'));
        $this->assertSame(42, $session->get('int'));
        $this->assertSame(3.14, $session->get('float'));
        $this->assertTrue($session->get('bool'));
        $this->assertSame(['a', 'b', 'c'], $session->get('array'));
    }

    public function testFlashWithArray()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $data = ['user' => 'john', 'action' => 'login'];
        $session->flash('event', $data);

        $this->assertSame($data, $session->getFlash('event'));
    }

    public function testForgetDoesNotAffectOtherKeys()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('keep', 'this');
        $session->set('remove', 'that');

        $session->forget('remove');

        $this->assertSame('this', $session->get('keep'));
        $this->assertNull($session->get('remove'));
    }

    public function testGetReturnsNullByDefault()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $this->assertNull($session->get('nonexistent'));
    }

    public function testSetWithNull()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('nullable', null);
        $this->assertNull($session->get('nullable'));
    }

    public function testFlashDoesNotOverwriteRegularSessionData()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('key', 'regular');
        $session->flash('key', 'flash');

        $this->assertSame('regular', $session->get('key'));
        $this->assertSame('flash', $session->getFlash('key'));
    }

    public function testMultipleStartCallsWithCustomHandler()
    {
        $session = new Session();
        $callCount = 0;

        $handler = function() use (&$callCount) {
            $callCount++;
        };

        $session->start($handler);
        $session->start($handler);

        $this->assertSame(2, $callCount);
    }

    public function testSetWithEmptyString()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('empty', '');
        $this->assertSame('', $session->get('empty'));
    }

    public function testGetDefaultWithFalseValue()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('bool', false);
        $this->assertFalse($session->get('bool', true));
    }

    public function testGetDefaultWithZero()
    {
        $session = new Session();
        $session->start(function() {return null;});

        $session->set('zero', 0);
        $this->assertSame(0, $session->get('zero', 999));
    }

}