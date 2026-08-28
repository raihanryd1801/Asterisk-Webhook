#!/bin/bash

# ==========================================
# KONFIGURASI DATABASE
# ==========================================
DB_USER="crm_user"
DB_PASS="fid1234" # Sesuaikan password mysql anda
DB_NAME="crm_asterisk"     # Sesuaikan nama database anda
TOTAL_DATA=50000                 # Jumlah data dummy yang akan dimasukkan

echo "?? Mempersiapkan $TOTAL_DATA data dummy untuk tabel cdr_live..."

SQL_FILE="/tmp/insert_test_cdr.sql"
echo "USE $DB_NAME;" > "$SQL_FILE"
echo "SET autocommit=0;" >> "$SQL_FILE"
echo "INSERT INTO cdr_live (calldate, src, dst, duration, billsec, disposition, uniqueid) VALUES" >> "$SQL_FILE"

DISPO_ARR=("ANSWERED" "NO ANSWER" "BUSY" "FAILED" "CANCEL")

for ((i=1; i<=TOTAL_DATA; i++))
do
    RAND_SRC=$((100 + RANDOM % 50))
    RAND_DST=$((200 + RANDOM % 50))
    RAND_DUR=$((RANDOM % 300))
    RAND_BILL=$((RAND_DUR > 30 ? RAND_DUR - 15 : 0))
    
    RAND_DISPO=${DISPO_ARR[$RANDOM % ${#DISPO_ARR[@]}]}
    
    # Tanggal acak dalam 30 hari ke belakang
    RAND_DATE=$(date -d "-$((RANDOM % 30)) days" '+%Y-%m-%d %H:%M:%S')
    RAND_UID="test_uid_${i}_$(openssl rand -hex 4)"

    if [ $i -eq $TOTAL_DATA ]; then
        echo "('$RAND_DATE', '$RAND_SRC', '$RAND_DST', $RAND_DUR, $RAND_BILL, '$RAND_DISPO', '$RAND_UID');" >> "$SQL_FILE"
    else
        echo "('$RAND_DATE', '$RAND_SRC', '$RAND_DST', $RAND_DUR, $RAND_BILL, '$RAND_DISPO', '$RAND_UID')," >> "$SQL_FILE"
    fi

    if [ $((i % 10000)) -eq 0 ]; then
        echo "Menyiapkan $i baris data..."
    fi
done

echo "COMMIT;" >> "$SQL_FILE"

echo "?? Memasukkan data ke database MySQL (Bulk Insert)..."
time mysql -u "$DB_USER" -p"$DB_PASS" < "$SQL_FILE"
rm "$SQL_FILE"

echo "=========================================="
echo "? MENGETES KECEPATAN QUERY DASHBOARD"
echo "=========================================="

echo "Test 1: Menghitung Statistik Keseluruhan..."
time mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "
SELECT 
    COUNT(*) as total_calls,
    SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as all_answered
FROM cdr_live;
"

echo "Test 2: Menguji Group By Disposition (Chart Outcomes)..."
time mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "
SELECT disposition, COUNT(*) as total FROM cdr_live GROUP BY disposition;
"

echo "Test 3: Menguji Performa Tabel Agent Performance (Group by Extension)..."
time mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "
SELECT src as extension, COUNT(*) as total_calls, SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as connected_calls 
FROM cdr_live WHERE src != '' GROUP BY extension ORDER BY total_calls DESC LIMIT 50;
"

echo "? Selesai! Perhatikan waktu (real/user/sys) di atas. Jika di bawah 0.5 detik, server Abang sangat kuat!"
