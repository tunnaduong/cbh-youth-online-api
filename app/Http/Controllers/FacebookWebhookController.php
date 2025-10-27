<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends Controller
{
  /**
   * Handle Facebook webhook verification and events
   *
   * This controller handles two types of requests from Facebook:
   * 1. GET: Verification during webhook setup (hub.verify_token & hub.challenge)
   * 2. POST: Receiving actual webhook events
   */

  // Store your verify token (match this with the token you set in Facebook Developer Console)
  private const VERIFY_TOKEN = '8f4fece8-14c9-4b65-87c3-797f2b8a4cf0';

  /**
   * Main webhook handler - processes both verification (GET) and events (POST)
   *
   * @param Request $request
   * @return \Illuminate\Http\Response
   */
  public function handleWebhook(Request $request)
  {
    // Handle verification (GET request)
    if ($request->isMethod('get')) {
      return $this->verify($request);
    }

    // Handle webhook events (POST request)
    if ($request->isMethod('post')) {
      return $this->handleEvent($request);
    }

    return response('Method not allowed', 405);
  }

  /**
   * Verify webhook endpoint
   * Facebook will send a GET request to verify your webhook URL
   *
   * @param Request $request
   * @return \Illuminate\Http\Response
   */
  private function verify(Request $request)
  {
    // Facebook sends verification as GET request with these parameters:
    // hub.mode, hub.verify_token, hub.challenge

    $mode = $request->query('hub_mode');
    $token = $request->query('hub_verify_token');
    $challenge = $request->query('hub_challenge');

    // Check if this is a verification request
    if ($mode === 'subscribe') {
      // Verify the token matches
      if ($token === self::VERIFY_TOKEN) {
        Log::info('Facebook webhook verified successfully');

        // Return the challenge string to confirm verification
        return response($challenge, 200)
          ->header('Content-Type', 'text/plain');
      } else {
        Log::warning('Facebook webhook verification failed: Invalid token', [
          'received_token' => $token,
          'expected_token' => self::VERIFY_TOKEN
        ]);

        return response('Invalid verify token', 403);
      }
    }

    return response('Invalid request', 400);
  }

  /**
   * Handle webhook events
   * Facebook will send POST requests with actual webhook data
   *
   * @param Request $request
   * @return \Illuminate\Http\Response
   */
  private function handleEvent(Request $request)
  {
    try {
      // Log the raw event data for debugging
      $rawData = $request->getContent();
      Log::info('Facebook webhook event received', [
        'headers' => $request->headers->all(),
        'raw_data' => $rawData
      ]);

      // Parse the event data
      $data = $request->all();

      // Handle different types of events
      if (isset($data['entry'])) {
        foreach ($data['entry'] as $entry) {
          $this->processWebhookEntry($entry);
        }
      }

      // Always return 200 OK to acknowledge receipt
      return response('EVENT_RECEIVED', 200);

    } catch (\Exception $e) {
      Log::error('Error processing Facebook webhook event', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // Still return 200 to prevent Facebook from retrying
      return response('ERROR_PROCESSING', 200);
    }
  }

  /**
   * Process individual webhook entry
   *
   * @param array $entry
   * @return void
   */
  private function processWebhookEntry(array $entry)
  {
    // Extract common fields
    $pageId = $entry['id'] ?? null;
    $time = $entry['time'] ?? null;

    Log::info('Processing Facebook webhook entry', [
      'page_id' => $pageId,
      'time' => $time
    ]);

    // Handle messaging events
    if (isset($entry['messaging'])) {
      foreach ($entry['messaging'] as $messagingEvent) {
        $this->handleMessagingEvent($messagingEvent);
      }
    }

    // Handle other event types (feed, changes, etc.)
    if (isset($entry['changes'])) {
      foreach ($entry['changes'] as $change) {
        $this->handleChangeEvent($change);
      }
    }

    // Add more event type handlers as needed
  }

  /**
   * Handle messaging events from Facebook
   *
   * @param array $messagingEvent
   * @return void
   */
  private function handleMessagingEvent(array $messagingEvent)
  {
    $senderId = $messagingEvent['sender']['id'] ?? null;
    $recipientId = $messagingEvent['recipient']['id'] ?? null;

    Log::info('Facebook messaging event received', [
      'sender_id' => $senderId,
      'recipient_id' => $recipientId
    ]);

    // Handle message received
    if (isset($messagingEvent['message'])) {
      $message = $messagingEvent['message'];
      $this->handleMessage($senderId, $message);
    }

    // Handle postback (button clicks, etc.)
    if (isset($messagingEvent['postback'])) {
      $this->handlePostback($senderId, $messagingEvent['postback']);
    }

    // Handle delivery confirmation
    if (isset($messagingEvent['delivery'])) {
      $this->handleDelivery($senderId, $messagingEvent['delivery']);
    }

    // Handle read receipts
    if (isset($messagingEvent['read'])) {
      $this->handleRead($senderId, $messagingEvent['read']);
    }
  }

  /**
   * Handle incoming message
   *
   * @param string $senderId
   * @param array $message
   * @return void
   */
  private function handleMessage(string $senderId, array $message)
  {
    $messageId = $message['mid'] ?? null;
    $text = $message['text'] ?? null;
    $attachments = $message['attachments'] ?? [];

    Log::info('Facebook message received', [
      'sender_id' => $senderId,
      'message_id' => $messageId,
      'text' => $text,
      'attachments' => $attachments
    ]);

    // TODO: Add your message handling logic here
    // For example: send auto-reply, save to database, trigger notification, etc.
  }

  /**
   * Handle postback events (button clicks, quick replies)
   *
   * @param string $senderId
   * @param array $postback
   * @return void
   */
  private function handlePostback(string $senderId, array $postback)
  {
    $payload = $postback['payload'] ?? null;
    $title = $postback['title'] ?? null;

    Log::info('Facebook postback received', [
      'sender_id' => $senderId,
      'payload' => $payload,
      'title' => $title
    ]);

    // TODO: Add your postback handling logic here
  }

  /**
   * Handle delivery confirmation
   *
   * @param string $senderId
   * @param array $delivery
   * @return void
   */
  private function handleDelivery(string $senderId, array $delivery)
  {
    $messageIds = $delivery['mids'] ?? [];
    $watermark = $delivery['watermark'] ?? null;

    Log::info('Facebook delivery confirmation', [
      'sender_id' => $senderId,
      'message_ids' => $messageIds,
      'watermark' => $watermark
    ]);
  }

  /**
   * Handle read receipt
   *
   * @param string $senderId
   * @param array $read
   * @return void
   */
  private function handleRead(string $senderId, array $read)
  {
    $watermark = $read['watermark'] ?? null;

    Log::info('Facebook read receipt', [
      'sender_id' => $senderId,
      'watermark' => $watermark
    ]);
  }

  /**
   * Handle change events (page changes, feed changes, etc.)
   *
   * @param array $change
   * @return void
   */
  private function handleChangeEvent(array $change)
  {
    $value = $change['value'] ?? [];
    $field = $change['field'] ?? null;

    Log::info('Facebook change event', [
      'field' => $field,
      'value' => $value
    ]);

    // TODO: Add your change handling logic here
  }
}

