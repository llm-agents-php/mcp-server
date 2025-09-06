<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Session;

use Mcp\Server\Contracts\SessionHandlerInterface;
use Mcp\Server\Session\Session;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    private SessionHandlerInterface $handler;
    private Session $session;
    private string $sessionId;

    protected function setUp(): void
    {
        $this->handler = Mockery::mock(SessionHandlerInterface::class);
        $this->sessionId = 'test-session-id';
        
        // Default handler behavior - no existing session data
        $this->handler->shouldReceive('read')->with($this->sessionId)->andReturn(false)->byDefault();
        
        $this->session = new Session($this->handler, $this->sessionId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_constructor_creates_empty_session(): void
    {
        $this->assertEquals($this->sessionId, $this->session->getId());
        $this->assertSame($this->handler, $this->session->getHandler());
        $this->assertEmpty($this->session->all());
    }

    public function test_constructor_loads_existing_session_data(): void
    {
        $existingData = ['user_id' => 123, 'username' => 'test'];
        $jsonData = json_encode($existingData);
        
        $handler = Mockery::mock(SessionHandlerInterface::class);
        $handler->shouldReceive('read')->with($this->sessionId)->andReturn($jsonData);
        
        $session = new Session($handler, $this->sessionId);
        
        $this->assertEquals(123, $session->get('user_id'));
        $this->assertEquals('test', $session->get('username'));
    }

    public function test_constructor_with_provided_data(): void
    {
        $data = ['initialized' => true, 'client_info' => ['name' => 'test']];
        
        $session = new Session($this->handler, $this->sessionId, $data);
        
        $this->assertTrue($session->get('initialized'));
        $this->assertEquals(['name' => 'test'], $session->get('client_info'));
    }

    public function test_retrieve_returns_null_for_non_existent_session(): void
    {
        $handler = Mockery::mock(SessionHandlerInterface::class);
        $handler->shouldReceive('read')->with('non-existent')->andReturn(false);
        
        $result = Session::retrieve('non-existent', $handler);
        
        $this->assertNull($result);
    }

    public function test_retrieve_returns_null_for_invalid_json(): void
    {
        $handler = Mockery::mock(SessionHandlerInterface::class);
        $handler->shouldReceive('read')->with('invalid-json')->andReturn('invalid-json-data');
        
        $result = Session::retrieve('invalid-json', $handler);
        
        $this->assertNull($result);
    }

    public function test_retrieve_returns_session_for_valid_data(): void
    {
        $sessionData = ['user_id' => 123, 'initialized' => true];
        $jsonData = json_encode($sessionData);
        
        $handler = Mockery::mock(SessionHandlerInterface::class);
        $handler->shouldReceive('read')->with('valid-session')->andReturn($jsonData);
        
        $result = Session::retrieve('valid-session', $handler);
        
        $this->assertInstanceOf(Session::class, $result);
        $this->assertEquals('valid-session', $result->getId());
        $this->assertEquals(123, $result->get('user_id'));
    }

    public function test_save_writes_to_handler(): void
    {
        $this->session->set('test_key', 'test_value');
        
        $this->handler->shouldReceive('write')
            ->with($this->sessionId, Mockery::on(function ($json) {
                $data = json_decode($json, true);
                return $data['test_key'] === 'test_value';
            }))
            ->andReturn(true);
        
        $this->session->save();
    }

    public function test_get_returns_value_for_existing_key(): void
    {
        $this->session->set('test_key', 'test_value');
        
        $result = $this->session->get('test_key');
        
        $this->assertEquals('test_value', $result);
    }

    public function test_get_returns_default_for_non_existent_key(): void
    {
        $result = $this->session->get('non_existent', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }

    public function test_get_supports_dot_notation(): void
    {
        $this->session->set('user.profile.name', 'John Doe');
        
        $result = $this->session->get('user.profile.name');
        
        $this->assertEquals('John Doe', $result);
    }

    public function test_set_stores_value(): void
    {
        $this->session->set('test_key', 'test_value');
        
        $this->assertEquals('test_value', $this->session->get('test_key'));
    }

    public function test_set_supports_dot_notation(): void
    {
        $this->session->set('user.profile.name', 'John Doe');
        $this->session->set('user.profile.age', 30);
        
        $this->assertEquals('John Doe', $this->session->get('user.profile.name'));
        $this->assertEquals(30, $this->session->get('user.profile.age'));
        $this->assertEquals(['name' => 'John Doe', 'age' => 30], $this->session->get('user.profile'));
    }

    public function test_set_with_overwrite_false_does_not_overwrite_existing(): void
    {
        $this->session->set('test_key', 'original_value');
        $this->session->set('test_key', 'new_value', false);
        
        $this->assertEquals('original_value', $this->session->get('test_key'));
    }

    public function test_set_with_overwrite_false_sets_non_existent_key(): void
    {
        $this->session->set('new_key', 'new_value', false);
        
        $this->assertEquals('new_value', $this->session->get('new_key'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->session->set('test_key', 'test_value');
        
        $this->assertTrue($this->session->has('test_key'));
    }

    public function test_has_returns_false_for_non_existent_key(): void
    {
        $this->assertFalse($this->session->has('non_existent'));
    }

    public function test_has_supports_dot_notation(): void
    {
        $this->session->set('user.profile.name', 'John Doe');
        
        $this->assertTrue($this->session->has('user.profile.name'));
        $this->assertTrue($this->session->has('user.profile'));
        $this->assertFalse($this->session->has('user.profile.email'));
    }

    public function test_forget_removes_key(): void
    {
        $this->session->set('test_key', 'test_value');
        $this->assertTrue($this->session->has('test_key'));
        
        $this->session->forget('test_key');
        
        $this->assertFalse($this->session->has('test_key'));
    }

    public function test_forget_supports_dot_notation(): void
    {
        $this->session->set('user.profile.name', 'John Doe');
        $this->session->set('user.profile.age', 30);
        
        $this->session->forget('user.profile.name');
        
        $this->assertFalse($this->session->has('user.profile.name'));
        $this->assertTrue($this->session->has('user.profile.age'));
    }

    public function test_clear_removes_all_data(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');
        
        $this->session->clear();
        
        $this->assertEmpty($this->session->all());
    }

    public function test_pull_returns_and_removes_value(): void
    {
        $this->session->set('test_key', 'test_value');
        
        $result = $this->session->pull('test_key');
        
        $this->assertEquals('test_value', $result);
        $this->assertFalse($this->session->has('test_key'));
    }

    public function test_pull_returns_default_for_non_existent_key(): void
    {
        $result = $this->session->pull('non_existent', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }

    public function test_pull_supports_dot_notation(): void
    {
        $this->session->set('user.profile.name', 'John Doe');
        
        $result = $this->session->pull('user.profile.name', 'default');
        
        $this->assertEquals('John Doe', $result);
        $this->assertFalse($this->session->has('user.profile.name'));
    }

    public function test_all_returns_all_data(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        
        foreach ($data as $key => $value) {
            $this->session->set($key, $value);
        }
        
        $result = $this->session->all();
        
        $this->assertEquals($data, $result);
    }

    public function test_hydrate_merges_with_defaults(): void
    {
        $customData = ['custom_key' => 'custom_value', 'initialized' => true];
        
        $this->session->hydrate($customData);
        
        $this->assertEquals('custom_value', $this->session->get('custom_key'));
        $this->assertTrue($this->session->get('initialized'));
        $this->assertNull($this->session->get('client_info'));
        $this->assertEmpty($this->session->get('message_queue'));
    }

    public function test_hydrate_removes_id_field(): void
    {
        $dataWithId = ['id' => 'should-be-removed', 'test_key' => 'test_value'];
        
        $this->session->hydrate($dataWithId);
        
        $this->assertFalse($this->session->has('id'));
        $this->assertEquals('test_value', $this->session->get('test_key'));
    }

    public function test_queue_message_adds_to_queue(): void
    {
        $this->session->queueMessage('test message 1');
        $this->session->queueMessage('test message 2');
        
        $this->assertTrue($this->session->hasQueuedMessages());
        $this->assertCount(2, $this->session->get('message_queue'));
    }

    public function test_dequeue_messages_returns_and_clears_queue(): void
    {
        $this->session->queueMessage('message 1');
        $this->session->queueMessage('message 2');
        
        $messages = $this->session->dequeueMessages();
        
        $this->assertEquals(['message 1', 'message 2'], $messages);
        $this->assertFalse($this->session->hasQueuedMessages());
        $this->assertEmpty($this->session->get('message_queue'));
    }

    public function test_has_queued_messages_returns_correct_status(): void
    {
        $this->assertFalse($this->session->hasQueuedMessages());
        
        $this->session->queueMessage('test message');
        $this->assertTrue($this->session->hasQueuedMessages());
        
        $this->session->dequeueMessages();
        $this->assertFalse($this->session->hasQueuedMessages());
    }

    public function test_json_serialize_returns_all_data(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');
        
        $result = $this->session->jsonSerialize();
        
        $this->assertEquals($this->session->all(), $result);
    }

    public function test_session_can_be_json_encoded(): void
    {
        $this->session->set('test_key', 'test_value');
        
        $json = json_encode($this->session);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('test_value', $decoded['test_key']);
    }

    public function test_complex_nested_operations(): void
    {
        // Set nested data
        $this->session->set('app.settings.theme', 'dark');
        $this->session->set('app.settings.language', 'en');
        $this->session->set('app.user.id', 123);
        
        // Test nested access
        $this->assertEquals('dark', $this->session->get('app.settings.theme'));
        $this->assertEquals(['theme' => 'dark', 'language' => 'en'], $this->session->get('app.settings'));
        
        // Test nested removal
        $this->session->forget('app.settings.theme');
        $this->assertFalse($this->session->has('app.settings.theme'));
        $this->assertTrue($this->session->has('app.settings.language'));
        
        // Test nested pull
        $userId = $this->session->pull('app.user.id');
        $this->assertEquals(123, $userId);
        $this->assertFalse($this->session->has('app.user.id'));
    }
}
