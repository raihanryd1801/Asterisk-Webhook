#!/bin/bash

# ==========================================
# KONFIGURASI DATABASE
# ==========================================
DB_USER="crm_user"
DB_PASS="fid1234"
DB_NAME="crm_asterisk"

TOTAL_DATA=1000000
BATCH_SIZE=5000
YEAR=2026

DISPO_ARR=("ANSWERED" "NO ANSWER" "BUSY" "FAILED" "CANCEL")

echo "=========================================="
echo "GENERATE CDR DUMMY"
echo "=========================================="
echo "Database   : $DB_NAME"
echo "Tahun      : $YEAR"
echo "Total Data : $TOTAL_DATA"
echo "Per Bulan  : ±83333"
echo "Batch      : $BATCH_SIZE"
echo "=========================================="
echo ""

START_TIME=$(date +%s)
TOTAL_INSERTED=0

# ==========================================
# LOOP JANUARI - DESEMBER
# ==========================================
for MONTH in {1..12}
do
    MONTH_DATA=$((TOTAL_DATA / 12))

    # Sisa data masuk Desember
    if [ "$MONTH" -eq 12 ]; then
        MONTH_DATA=$((MONTH_DATA + TOTAL_DATA % 12))
    fi

    MONTH_NAME=$(LC_TIME=C date -d "$YEAR-$MONTH-01" '+%B')

    echo ""
    echo "------------------------------------------"
    echo "Bulan : $MONTH_NAME ($YEAR)"
    echo "Data  : $MONTH_DATA"
    echo "------------------------------------------"

    MONTH_INSERTED=0

    # ======================================
    # BATAS WAKTU BULAN
    # ======================================
    START_DATE=$(date -d "$YEAR-$MONTH-01" '+%s')
    NEXT_MONTH_DATE=$(date -d "$YEAR-$MONTH-01 +1 month" '+%s')

    RANGE=$((NEXT_MONTH_DATE - START_DATE))

    # ======================================
    # BATCH
    # ======================================
    while [ "$MONTH_INSERTED" -lt "$MONTH_DATA" ]
    do

        REMAINING=$((MONTH_DATA - MONTH_INSERTED))

        if [ "$REMAINING" -lt "$BATCH_SIZE" ]; then
            CURRENT_BATCH=$REMAINING
        else
            CURRENT_BATCH=$BATCH_SIZE
        fi

        # ==================================
        # GENERATE SQL
        # ==================================
        SQL="INSERT INTO cdr_live
(calldate, src, dst, duration, billsec, disposition, uniqueid)
VALUES"

        for ((j=1; j<=CURRENT_BATCH; j++))
        do

            # Random timestamp dalam bulan
            RANDOM_SECOND=$((RANDOM % RANGE))

            RAND_TIMESTAMP=$((START_DATE + RANDOM_SECOND))

            RAND_DATE=$(date -d "@$RAND_TIMESTAMP" '+%Y-%m-%d %H:%M:%S')

            # Random source
            RAND_SRC=$((100 + RANDOM % 50))

            # Random destination
            RAND_DST=$((200 + RANDOM % 50))

            # Random duration
            RAND_DUR=$((RANDOM % 300))

            # Billsec
            if [ "$RAND_DUR" -gt 30 ]; then
                RAND_BILL=$((RAND_DUR - 15))
            else
                RAND_BILL=0
            fi

            # Disposition
            RAND_DISPO=${DISPO_ARR[$RANDOM % ${#DISPO_ARR[@]}]}

            # Unique ID
            RAND_UID="test_${YEAR}${MONTH}_${MONTH_INSERTED}_${j}_${RANDOM}${RANDOM}"

            if [ "$j" -eq "$CURRENT_BATCH" ]; then
                SQL+="('$RAND_DATE','$RAND_SRC','$RAND_DST',$RAND_DUR,$RAND_BILL,'$RAND_DISPO','$RAND_UID');"
            else
                SQL+="('$RAND_DATE','$RAND_SRC','$RAND_DST',$RAND_DUR,$RAND_BILL,'$RAND_DISPO','$RAND_UID'),"
            fi

        done

        # ==================================
        # KIRIM LANGSUNG KE MYSQL
        # ==================================
        printf '%s\n' "$SQL" | mysql \
            -u"$DB_USER" \
            -p"$DB_PASS" \
            -D "$DB_NAME"

        if [ $? -ne 0 ]; then
            echo ""
            echo "ERROR!"
            echo "Gagal insert bulan $MONTH_NAME"
            exit 1
        fi

        MONTH_INSERTED=$((MONTH_INSERTED + CURRENT_BATCH))
        TOTAL_INSERTED=$((TOTAL_INSERTED + CURRENT_BATCH))

        echo "  $MONTH_NAME : $MONTH_INSERTED / $MONTH_DATA"
        echo "  TOTAL       : $TOTAL_INSERTED / $TOTAL_DATA"

    done

done

# ==========================================
# SELESAI
# ==========================================
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))

echo ""
echo "=========================================="
echo "INSERT SELESAI"
echo "=========================================="
echo "Total Data : $TOTAL_INSERTED"
echo "Waktu      : ${ELAPSED} detik"
echo "=========================================="

# ==========================================
# VERIFIKASI PER BULAN
# ==========================================
echo ""
echo "=========================================="
echo "VERIFIKASI DATA PER BULAN"
echo "=========================================="

mysql \
    -u"$DB_USER" \
    -p"$DB_PASS" \
    -D "$DB_NAME" \
    -e "
SELECT
    DATE_FORMAT(calldate, '%Y-%m') AS bulan,
    COUNT(*) AS total
FROM cdr_live
WHERE calldate >= '$YEAR-01-01'
  AND calldate < '$((YEAR + 1))-01-01'
GROUP BY DATE_FORMAT(calldate, '%Y-%m')
ORDER BY bulan;
"

# ==========================================
# TEST QUERY DASHBOARD
# ==========================================
echo ""
echo "=========================================="
echo "TEST QUERY DASHBOARD"
echo "=========================================="

echo ""
echo "Test 1: Total Calls + Answered"

time mysql \
    -u"$DB_USER" \
    -p"$DB_PASS" \
    -D "$DB_NAME" \
    -e "
SELECT
    COUNT(*) AS total_calls,
    SUM(disposition = 'ANSWERED') AS answered
FROM cdr_live;
"

echo ""
echo "Test 2: Group By Disposition"

time mysql \
    -u"$DB_USER" \
    -p"$DB_PASS" \
    -D "$DB_NAME" \
    -e "
SELECT
    disposition,
    COUNT(*) AS total
FROM cdr_live
GROUP BY disposition;
"

echo ""
echo "Test 3: Agent Performance"

time mysql \
    -u"$DB_USER" \
    -p"$DB_PASS" \
    -D "$DB_NAME" \
    -e "
SELECT
    src AS extension,
    COUNT(*) AS total_calls,
    SUM(disposition = 'ANSWERED') AS connected_calls
FROM cdr_live
WHERE src != ''
GROUP BY src
ORDER BY total_calls DESC
LIMIT 50;
"

echo ""
echo "=========================================="
echo "SELESAI"
echo "=========================================="
