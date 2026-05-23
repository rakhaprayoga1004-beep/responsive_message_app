<?php
// api/test_dashboard.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$response = [
    'success' => true,
    'data' => [
        'stats' => [
            'total_responded' => 24,
            'pending_review' => 8,
            'reviewed' => 12,
            'avg_response_time' => 4,
            'fastest_responder' => 'Budi Santoso (2 jam)',
            'total_guru' => 6,
            'total_message_types' => 5,
        ],
        'messages' => [],
        'guru_performances' => [
            [
                'id' => 1,
                'nama_lengkap' => 'Dr. Ahmad Hidayat, M.Pd',
                'user_type' => 'Guru_BK',
                'total_messages' => 15,
                'pending_messages' => 3,
                'responded_messages' => 10,
                'expired_messages' => 2,
                'avg_response_hours' => 2.5,
            ],
            [
                'id' => 2,
                'nama_lengkap' => 'Siti Nurjanah, S.Pd',
                'user_type' => 'Guru_Kesiswaan',
                'total_messages' => 12,
                'pending_messages' => 2,
                'responded_messages' => 9,
                'expired_messages' => 1,
                'avg_response_hours' => 3.2,
            ],
        ],
        'message_type_stats' => [
            [
                'id' => 1,
                'jenis_pesan' => 'Konsultasi Akademik',
                'responder_type' => 'Guru_BK',
                'total_messages' => 8,
                'responded_messages' => 7,
                'pending_messages' => 1,
                'expired_messages' => 0,
                'avg_response_hours' => 2.0,
            ],
            [
                'id' => 2,
                'jenis_pesan' => 'Pelanggaran Siswa',
                'responder_type' => 'Guru_Kesiswaan',
                'total_messages' => 6,
                'responded_messages' => 5,
                'pending_messages' => 1,
                'expired_messages' => 0,
                'avg_response_hours' => 3.5,
            ],
        ],
        'guru_list' => [
            ['id' => 1, 'nama_lengkap' => 'Dr. Ahmad Hidayat, M.Pd', 'user_type' => 'Guru_BK'],
            ['id' => 2, 'nama_lengkap' => 'Siti Nurjanah, S.Pd', 'user_type' => 'Guru_Kesiswaan'],
            ['id' => 3, 'nama_lengkap' => 'Budi Santoso, S.Pd', 'user_type' => 'Guru_Humas'],
        ],
        'total' => 0,
        'total_pages' => 1,
    ]
];

echo json_encode($response);
?>