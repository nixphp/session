<?php

namespace Tests\Unit;

use NixPHP\Session\Support\Session;
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

}