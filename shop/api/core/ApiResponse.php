// API Response Handler (api/core/ApiResponse.php)
class ApiResponse {
    public static function send($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        exit;
    }

    public static function error($message, $status = 400) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        exit;
    }
}
