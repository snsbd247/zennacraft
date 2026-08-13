#!/usr/bin/env bash
#
# Proves, with real processes and a real (throwaway) database, that:
#   1. An OTP request is genuinely delivered (status=sent), not just queued.
#   2. It stays fast even with hundreds of bulk jobs pending, because the
#      dedicated `otp` worker never touches the `bulk` queue at all.
#   3. If no worker is running, Studio diagnostics detects and reports it
#      instead of staying green.
#
# Safe to run anywhere: uses its own throwaway sqlite file and array
# cache/session drivers, never touches your real .env database. Cleans up
# after itself (kills workers, deletes the scratch DB) even on failure.
#
# Usage: ./deploy/verify-queue-priority.sh

set -e

SCRATCH_DIR="$(mktemp -d)"
DBFILE="$SCRATCH_DIR/queue-proof.sqlite"
touch "$DBFILE"

export DB_CONNECTION=sqlite
export DB_DATABASE="$DBFILE"
export APP_ENV=local
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=database
export SMS_DRIVER=log

cleanup() {
    [ -n "$OTP_PID" ] && kill "$OTP_PID" 2>/dev/null || true
    rm -rf "$SCRATCH_DIR"
}
trap cleanup EXIT

echo "== Setting up an isolated throwaway database =="
php artisan migrate --force >/dev/null 2>&1
php artisan db:seed --class=GeneralSettingsSeeder --force >/dev/null 2>&1

echo "== Seeding 500 real bulk messages onto the bulk queue (no worker consuming them yet) =="
php artisan tinker --execute="
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\Communication\Models\CommunicationMessage;
\$service = app(CommunicationService::class);
for (\$i = 0; \$i < 500; \$i++) {
    \$message = \$service->createFromTemplate('sms', '017'.str_pad((string) \$i, 8, '0', STR_PAD_LEFT), 'coupon_campaign', ['customer_name' => 'Bulk Customer '.\$i, 'coupon_code' => 'BULK500'], []);
    \$service->queueMessage(\$message, true, CommunicationMessage::QUEUE_TIER_BULK);
}
" >/dev/null 2>&1

BULK_DEPTH_BEFORE=$(sqlite3 "$DBFILE" "SELECT COUNT(*) FROM jobs WHERE queue='bulk';")
echo "Bulk queue depth: $BULK_DEPTH_BEFORE"

echo ""
echo "== [1/3] Starting ONLY the dedicated otp worker (bulk/general worker is NOT running) =="
php artisan queue:work database --queue=otp --sleep=1 --tries=1 > "$SCRATCH_DIR/otp-worker.log" 2>&1 &
OTP_PID=$!
sleep 1.5

echo "== Triggering a real customer OTP request =="
START_TS=$(date +%s.%N)
MESSAGE_ID=$(php artisan tinker --execute="
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerOtpService;
use App\Modules\Communication\Models\CommunicationMessage;
\$customer = Customer::create(['phone' => '01799999998', 'name' => 'Verify Script Customer']);
app(CustomerOtpService::class)->generateOtp(\$customer);
echo CommunicationMessage::where('template', 'customer_otp')->latest()->first()->id;
" 2>&1 | tail -1)

DEADLINE=$((SECONDS + 15))
STATUS="unknown"
while [ $SECONDS -lt $DEADLINE ]; do
    STATUS=$(sqlite3 "$DBFILE" "SELECT status FROM communication_messages WHERE id = $MESSAGE_ID;")
    [ "$STATUS" = "sent" ] && break
    sleep 0.1
done
END_TS=$(date +%s.%N)
ELAPSED=$(echo "$END_TS - $START_TS" | bc)
BULK_DEPTH_AFTER=$(sqlite3 "$DBFILE" "SELECT COUNT(*) FROM jobs WHERE queue='bulk';")
UNTOUCHED=$(sqlite3 "$DBFILE" "SELECT COUNT(*) FROM jobs WHERE queue='bulk' AND reserved_at IS NULL;")

echo ""
echo "RESULT: OTP status=$STATUS in ${ELAPSED}s, while bulk queue still had $BULK_DEPTH_AFTER jobs ($UNTOUCHED of them never even claimed)."
if [ "$STATUS" != "sent" ] || [ "$BULK_DEPTH_AFTER" != "$BULK_DEPTH_BEFORE" ]; then
    echo "FAIL: expected sent + untouched bulk queue."
    exit 1
fi

kill "$OTP_PID" 2>/dev/null || true
unset OTP_PID
sleep 0.5

echo ""
echo "== [2/3] With the worker now stopped, queue one more OTP job and let it go stale =="
php artisan tinker --execute="
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerOtpService;
use Illuminate\Support\Facades\DB;
\$customer = Customer::create(['phone' => '01799999997', 'name' => 'Verify Script Dead Worker']);
app(CustomerOtpService::class)->generateOtp(\$customer);
DB::table('jobs')->where('queue', 'otp')->update(['available_at' => now()->timestamp - 200, 'created_at' => now()->timestamp - 200]);
" >/dev/null 2>&1

echo "== [3/3] Checking Studio diagnostics detect the dead worker =="
DIAG_STATUS=$(php artisan tinker --execute="
echo app(\App\Modules\System\Services\SystemDiagnosticsService::class)->report()['queue_health']['otp_queue_health']['status'];
" 2>&1 | tail -1)

echo "otp_queue_health status with no worker running: $DIAG_STATUS"
if [ "$DIAG_STATUS" != "action_needed" ]; then
    echo "FAIL: expected action_needed, diagnostics did not detect the outage."
    exit 1
fi

echo ""
echo "ALL CHECKS PASSED."
