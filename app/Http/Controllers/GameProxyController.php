<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\GameProxyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameProxyController extends Controller
{
    public function __construct(private GameProxyService $proxy) {}

    /**
     * Issue a play ticket: the URL to put in the game's iframe. Ad-free, and the
     * player's cookies are kept on the server. Clients that cannot get a ticket
     * keep using the game's own iframe_url from GET /games/{slug}.
     */
    public function ticket(Request $request, string $slug)
    {
        $game = Game::where('slug', $slug)->where('is_active', true)->firstOrFail();

        if (! $this->proxy->supports($game)) {
            return response()->json(['message' => 'Game này chưa hỗ trợ chế độ không quảng cáo.'], 422);
        }

        [$token, $expires] = $this->proxy->issueTicket(Auth::id(), $game->id);

        return response()->json([
            'url' => $this->playBase($token),
            'expires_at' => date(DATE_ATOM, $expires),
        ]);
    }

    /**
     * The game's localStorage, posted by the page in the frame. Sent as text/plain
     * (a "simple" request, so no CORS preflight from the sandboxed frame), and the
     * whole map replaces the stored one. The response is not read by the page.
     */
    public function save(Request $request, string $ticket)
    {
        $claims = $this->proxy->verifyTicket($ticket);
        $game = $claims ? Game::where('id', $claims['game_id'])->where('is_active', true)->first() : null;
        if (! $game) {
            abort(404);
        }

        $data = json_decode($request->getContent(), true);
        if (! $this->proxy->saveStorage($game->id, $claims['user_id'], $data)) {
            return response()->json(['message' => 'Dữ liệu lưu của game không hợp lệ.'], 422, ['Access-Control-Allow-Origin' => '*']);
        }

        return response('', 204, ['Access-Control-Allow-Origin' => '*', 'Cache-Control' => 'no-store']);
    }

    /**
     * The folder URL of one play ticket. url() drops a trailing slash, and the
     * page's <base> needs it, or relative files resolve one folder too high.
     */
    private function playBase(string $ticket): string
    {
        return rtrim(url('/v1.0/games/play/'.$ticket), '/').'/';
    }

    /**
     * Serve the game page (path empty) or one of its files, through the ticket.
     * The page is rewritten; files are passed through with cookies replayed.
     */
    public function serve(Request $request, string $ticket, ?string $path = '')
    {
        $claims = $this->proxy->verifyTicket($ticket);
        $game = $claims ? Game::where('id', $claims['game_id'])->where('is_active', true)->first() : null;
        if (! $game) {
            abort(404);
        }

        $isPage = ($path ?? '') === '';

        if ($isPage) {
            $upstream = $game->iframe_url;
        } else {
            $upstream = $this->proxy->assetUrl($game, $path, $request->getQueryString());
            if ($upstream === null) {
                abort(404);
            }
        }

        $file = $this->proxy->fetch($game, $claims['user_id'], $upstream);
        if ($file === null || $file['status'] >= 500) {
            return $isPage
              ? response('<p>Không tải được game. Vui lòng thử lại sau.</p>', 502, ['Content-Type' => 'text/html; charset=UTF-8'])
              : response()->json(['message' => 'Không tải được tệp của game.'], 502);
        }

        if ($file['status'] !== 200) {
            return response()->json(['message' => 'Không tìm thấy tệp.'], $file['status'] ?: 404);
        }

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($isPage) {
            $storage = $this->proxy->storageFor($game->id, $claims['user_id']);
            $html = $this->proxy->rewriteDocument($file['body'], $this->playBase($ticket), $game, $storage);

            return response($html, 200, $headers + [
                'Content-Type' => 'text/html; charset=UTF-8',
                // The ticket is in this URL; keep it out of Referer headers sent to the game's CDN.
                'Referrer-Policy' => 'no-referrer',
                'Content-Security-Policy' => GameProxyService::SANDBOX_CSP,
                'Cache-Control' => 'no-store',
            ]);
        }

        return response($file['body'], 200, $headers + [
            'Content-Type' => $file['type'],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
