<?php
/*
|--------------------------------------------------------------------------
| PromoController
|--------------------------------------------------------------------------
| Handles promo / exchange code redemption.
| Codes are created in the CMS admin panel and distributed externally.
|
| Each code can award Coins and/or Gems.
| Codes enforce: max_uses (global cap), one redemption per user,
| expiry date, and is_active flag.
|
| Endpoint:
|   redeem() POST /v1/promo/redeem — Body: { code }
|
| Dependencies: promo_codes, promo_code_redemptions,
|   coin_transactions, gem_transactions, users
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoController extends Controller
{
    /**
     * POST /v1/promo/redeem
     * Body: { code: "SUMMER50" }
     *
     * Returns the rewards granted on success,
     * or a descriptive error on failure.
     */
    public function redeem(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:32']);
        $user = $request->user();

        $code = DB::table('promo_codes')
            ->where('code', strtoupper(trim($data['code'])))
            ->where('is_active', true)
            ->first();

        if (! $code) {
            return response()->json(['message' => 'Invalid or inactive code.'], 404);
        }

        // Expiry check
        if ($code->expires_at && now()->gt($code->expires_at)) {
            return response()->json(['message' => 'This code has expired.'], 410);
        }

        // Global usage cap
        if ($code->used_count >= $code->max_uses) {
            return response()->json(['message' => 'Code has reached its usage limit.'], 410);
        }

        // Per-user uniqueness
        $alreadyUsed = DB::table('promo_code_redemptions')
            ->where('user_id', $user->id)
            ->where('promo_code_id', $code->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json(['message' => 'You have already used this code.'], 400);
        }

        DB::transaction(function () use ($user, $code) {
            $now = now();

            // Record redemption
            DB::table('promo_code_redemptions')->insert([
                'user_id'       => $user->id,
                'promo_code_id' => $code->id,
                'redeemed_at'   => $now,
            ]);

            // Increment global counter
            DB::table('promo_codes')
                ->where('id', $code->id)
                ->increment('used_count');

            // Award coins
            if ($code->coin_value > 0) {
                $user->increment('coin_balance', $code->coin_value);

                DB::table('coin_transactions')->insert([
                    'user_id'       => $user->id,
                    'type'          => 'bonus',
                    'amount'        => $code->coin_value,
                    'balance_after' => $user->fresh()->coin_balance,
                    'description'   => "Promo code: {$code->code}",
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            // Award gems
            if ($code->gem_value > 0) {
                $user->increment('gem_balance', $code->gem_value);

                DB::table('gem_transactions')->insert([
                    'user_id'       => $user->id,
                    'type'          => 'bonus',
                    'amount'        => $code->gem_value,
                    'balance_after' => $user->fresh()->gem_balance,
                    'description'   => "Promo code: {$code->code}",
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        });

        return response()->json([
            'success'     => true,
            'coins_added' => $code->coin_value,
            'gems_added'  => $code->gem_value,
            'new_coin_balance' => $user->fresh()->coin_balance,
            'new_gem_balance'  => $user->fresh()->gem_balance,
        ]);
    }
}
