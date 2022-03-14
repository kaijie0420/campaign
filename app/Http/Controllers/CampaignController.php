<?php

namespace App\Http\Controllers;

use App\Helpers\ImageRecognitionHelper;
use App\PurchaseTransaction;
use App\Voucher;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CampaignController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Log::debug(microtime(true));

        Voucher::where('redeemed', false)
            ->where('locked_at', '<', date('Y-m-d H:i:s', strtotime('-10 minutes')))
            ->update([
                'customer_id' => null,
                'locked_at' => null
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/check-eligibility",
     *     summary="Customer eligible check",
     *     description="Check for customer eligibility to redeem for voucher.",
     *     operationId="checkEligibility",
     *     security={{"bearer_token": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(
     *             @OA\Examples(example="eligible", value={"eligibility": true, "message": "Success."}, 
     *                 summary="Eligible"),
     *             @OA\Examples(example="eligible,locked", value={"eligibility": true, 
     *                 "message": "Voucher locked, proceed to validate."}, summary="Eligible, voucher locked"),
     *             @OA\Examples(example="not eligible,all redeemed", value={"eligibility": false, 
     *                 "error_message": "All vouchers redeemed."}, summary="Not eligible, all redeemed"),
     *             @OA\Examples(example="not eligible,all locked", value={"eligibility": false, 
     *                 "error_message": "All vouchers have been claimed."}, summary="Not eligible, all redeemed"),
     *             @OA\Examples(example="not eligible,redeemed", value={"eligibility": false, 
     *                 "error_message": "Redeemed."}, summary="Not eligible, all redeemed"),
     *             @OA\Examples(example="not eligible,transaction count", value={"eligibility": false, 
     *                 "error_message": "Less than 3 transactions in 30 days."}, summary="Not eligible, insufficient transaction count"),
     *             @OA\Examples(example="not eligible,transaction amount", value={"eligibility": false, 
     *                 "error_message": "Total transactions less than $100."}, summary="Not eligible, insufficient transaction amount"),
     *         )
     *     ),     
     * )
     */
    public function checkEligibility()
    {        
        if (Voucher::where('redeemed', true)->count() == 1000) {
            return response()->json([
                'eligibility' => false,
                'error_message' => 'All vouchers redeemed.'
            ]);
        }

        $unlockedVoucherCount = Voucher::getUnlockedVoucher()->count();
        if ($unlockedVoucherCount < 1) {
            return response()->json([
                'eligibility' => false,
                'error_message' => 'All vouchers have been claimed.'
            ]);
        }

        $voucher = Voucher::where('customer_id', Auth::user()->id)->first();
        if ($voucher) {
            if ($voucher->redeemed) {
                return response()->json([
                    'eligibility' => false,
                    'error_message' => 'Redeemed.'
                ]);
            } else {
                $time_diff = (strtotime(date('Y-m-d H:i:s')) - strtotime($voucher->locked_at)) / 60;

                if ($time_diff <= 10) {
                    return response()->json([
                        'eligibility' => true,
                        'message' => 'Voucher locked, proceed to validate.'
                    ]);
                }
            }
        }

        $query = PurchaseTransaction::where('customer_id', Auth::user()->id);

        $count = $query->count();
        if ($count < 3) {
            return response()->json([
                'eligibility' => false,
                'error_message' => 'Less than 3 transactions in 30 days.'
            ]);
        }

        $sum = $query->where('transaction_at', '>=', date('Y-m-d 00:00:00', strtotime('-30 days')))
                ->sum('total_spent');
        if ($sum < 100) {
            return response()->json([
                'eligibility' => false,
                'error_message' => 'Total transactions less than $100.'
            ]);
        }

        $skip = 0;
        $codes = Voucher::getUnlockedVoucher()->pluck('code');
        
        do {
            $code = $codes[$skip++];
            $lock = Cache::lock($code, 600);
        } while (!$lock->get());

        // sleep(2);
        
        Voucher::where('code', $code)
            ->update([
                'customer_id' => Auth::user()->id,
                'locked_at' => date('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'eligibility' => true,
            'message' => 'Success.'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/validate-photo",
     *     summary="Validate photo submission",
     *     description="Uploaded photo will be validated throught image recognition API. Voucher code will be issued for success validation.",
     *     operationId="validatePhoto",
     *     security={{"bearer_token": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     description="photo to upload",
     *                     property="photo",
     *                     type="file",
     *                 ),
     *                 required={"photo"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(
     *             @OA\Examples(example="success", value={"code": "qyg35xy5DWfJQJTl", "message": "Success."}, 
     *                 summary="Validation success"),
     *             @OA\Examples(example="eligible,locked", value={"error_message": "Image recoginition failed."}, 
     *                 summary="Validation failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="No voucher locked for user",
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Missing field",
     *     ),
     * )
     */
    public function validatePhoto(Request $request)
    {
        $this->validate($request, [
            'photo' => 'required|file|image'
        ]);

        $voucher = Voucher::where('customer_id', Auth::user()->id)
            ->where('redeemed', false)
            ->firstOrFail();

        Cache::forget($voucher->code);

        // $imageRecognition = new ImageRecognitionHelper($request->photo);
        // $result = $imageRecognition->validate();
        $result = ImageRecognitionHelper::validate();

        if ($result) {
            $voucher->redeemed = true;
            $voucher->save();

            return response()->json([
                'code' => $voucher->code,
                'message' => 'Success.'
            ]);
        } else {
            $voucher->customer_id = null;
            $voucher->locked_at = null;
            $voucher->save();

            return response()->json([
                'error_message' => 'Image recoginition failed.'
            ]);
        }
    }
}
