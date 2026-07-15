<?php
require_once __DIR__ . '/bootstrap.php';
require_once '../../includes/config.php';
require_once '../../includes/database.php';

// Set headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

try {
    switch ($action) {
        case 'list_posts':
            // List collaboration posts with pagination and filtering
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $perPage = isset($_GET['per_page']) ? min(50, max(1, intval($_GET['per_page']))) : 20;
            $category = $_GET['category'] ?? 'all';
            $sort = $_GET['sort'] ?? 'latest';
            $search = $_GET['search'] ?? '';

            $offset = ($page - 1) * $perPage;

            $query = $supabaseClient->from('collaboration_posts')
                ->select('*, user_profiles(name, avatar_url, role)', ['count' => 'exact']);

            // Apply filters
            if ($category !== 'all') {
                $query->eq('category', $category);
            }

            if (!empty($search)) {
                $query->or("title.ilike.%{$search}%,content.ilike.%{$search}%");
            }

            // Apply sorting
            switch ($sort) {
                case 'popular':
                    $query->order('likes_count', ['ascending' => false]);
                    break;
                case 'trending':
                    $query->order('views_count', ['ascending' => false]);
                    break;
                case 'latest':
                default:
                    $query->order('is_pinned', ['ascending' => false])
                          ->order('created_at', ['ascending' => false]);
                    break;
            }

            $posts = $query->range($offset, $offset + $perPage - 1)->execute();

            echo json_encode([
                'success' => true,
                'posts' => $posts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $posts['count'] ?? 0,
                    'total_pages' => ceil(($posts['count'] ?? 0) / $perPage)
                ]
            ]);
            break;

        case 'get_post':
            // Get single post details
            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            $post = $supabaseClient->from('collaboration_posts')
                ->select('*, user_profiles(name, avatar_url, role)')
                ->eq('id', $postId)
                ->single()
                ->execute();

            if (!$post) {
                throw new Exception('Post not found');
            }

            echo json_encode(['success' => true, 'post' => $post]);
            break;

        case 'create_post':
            // Create new collaboration post
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Invalid JSON data');
            }

            // Validate required fields
            if (empty($data['title']) || empty($data['content'])) {
                throw new Exception('Title and content are required');
            }

            $postData = [
                'user_id' => $userId,
                'title' => trim($data['title']),
                'content' => trim($data['content']),
                'category' => $data['category'] ?? 'general',
                'is_pinned' => false,
                'is_featured' => false,
                'likes_count' => 0,
                'comments_count' => 0,
                'views_count' => 0,
                'attachments' => json_encode($data['attachments'] ?? []),
                'created_at' => date('c'),
                'updated_at' => date('c')
            ];

            $result = $supabaseClient->from('collaboration_posts')->insert($postData)->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Post created successfully',
                'post' => $result[0] ?? null
            ]);
            break;

        case 'update_post':
            // Update existing post
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                throw new Exception('Method not allowed');
            }

            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Invalid JSON data');
            }

            // Check ownership or admin permissions
            $existingPost = $supabaseClient->from('collaboration_posts')
                ->select('user_id')
                ->eq('id', $postId)
                ->single()
                ->execute();

            if (!$existingPost) {
                throw new Exception('Post not found');
            }

            if ($existingPost['user_id'] !== $userId && !in_array($userRole, ['eb_admin', 'eb_president'])) {
                throw new Exception('Permission denied');
            }

            $updateData = [
                'title' => trim($data['title']) ?? $existingPost['title'],
                'content' => trim($data['content']) ?? $existingPost['content'],
                'category' => $data['category'] ?? $existingPost['category'],
                'attachments' => json_encode($data['attachments'] ?? json_decode($existingPost['attachments'] ?? '[]', true)),
                'updated_at' => date('c')
            ];

            $result = $supabaseClient->from('collaboration_posts')
                ->update($updateData)
                ->eq('id', $postId)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Post updated successfully'
            ]);
            break;

        case 'delete_post':
            // Delete post
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('Method not allowed');
            }

            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            // Check ownership or admin permissions
            $existingPost = $supabaseClient->from('collaboration_posts')
                ->select('user_id')
                ->eq('id', $postId)
                ->single()
                ->execute();

            if (!$existingPost) {
                throw new Exception('Post not found');
            }

            if ($existingPost['user_id'] !== $userId && !in_array($userRole, ['eb_admin', 'eb_president'])) {
                throw new Exception('Permission denied');
            }

            $supabaseClient->from('collaboration_posts')->delete()->eq('id', $postId)->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Post deleted successfully'
            ]);
            break;

        case 'like_post':
            // Like or unlike a post
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            // Check if user already liked this post
            $existingLike = $supabaseClient->from('collaboration_likes')
                ->select('*')
                ->eq('post_id', $postId)
                ->eq('user_id', $userId)
                ->single()
                ->execute();

            if ($existingLike) {
                // Unlike: remove like and decrement count
                $supabaseClient->from('collaboration_likes')
                    ->delete()
                    ->eq('post_id', $postId)
                    ->eq('user_id', $userId)
                    ->execute();

                $supabaseClient->from('collaboration_posts')
                    ->update(['likes_count' => $supabaseClient->raw('likes_count - 1')])
                    ->eq('id', $postId)
                    ->execute();

                echo json_encode([
                    'success' => true,
                    'action' => 'unliked',
                    'message' => 'Post unliked'
                ]);
            } else {
                // Like: add like and increment count
                $supabaseClient->from('collaboration_likes')->insert([
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'created_at' => date('c')
                ])->execute();

                $supabaseClient->from('collaboration_posts')
                    ->update(['likes_count' => $supabaseClient->raw('likes_count + 1')])
                    ->eq('id', $postId)
                    ->execute();

                echo json_encode([
                    'success' => true,
                    'action' => 'liked',
                    'message' => 'Post liked'
                ]);
            }
            break;

        case 'view_post':
            // Increment view count
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            $supabaseClient->from('collaboration_posts')
                ->update(['views_count' => $supabaseClient->raw('views_count + 1')])
                ->eq('id', $postId)
                ->execute();

            echo json_encode(['success' => true]);
            break;

        case 'report_post':
            // Report a post
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $reason = $data['reason'] ?? 'General violation';

            $supabaseClient->from('collaboration_reports')->insert([
                'post_id' => $postId,
                'user_id' => $userId,
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => date('c')
            ])->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Post reported successfully'
            ]);
            break;

        case 'get_comments':
            // Get comments for a post
            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            $comments = $supabaseClient->from('collaboration_comments')
                ->select('*, user_profiles(name, avatar_url)')
                ->eq('post_id', $postId)
                ->order('created_at', ['ascending' => true])
                ->execute();

            echo json_encode([
                'success' => true,
                'comments' => $comments
            ]);
            break;

        case 'add_comment':
            // Add comment to post
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                throw new Exception('Post ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['content'])) {
                throw new Exception('Comment content required');
            }

            $commentData = [
                'post_id' => $postId,
                'user_id' => $userId,
                'content' => trim($data['content']),
                'parent_id' => $data['parent_id'] ?? null,
                'created_at' => date('c')
            ];

            $result = $supabaseClient->from('collaboration_comments')->insert($commentData)->execute();

            // Increment comment count
            $supabaseClient->from('collaboration_posts')
                ->update(['comments_count' => $supabaseClient->raw('comments_count + 1')])
                ->eq('id', $postId)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment' => $result[0] ?? null
            ]);
            break;

        case 'get_categories':
            // Get available post categories
            $categories = [
                ['id' => 'projects', 'name' => 'Projects', 'description' => 'Collaborative projects and initiatives'],
                ['id' => 'ideas', 'name' => 'Ideas', 'description' => 'Share innovative ideas'],
                ['id' => 'help', 'name' => 'Help Needed', 'description' => 'Request assistance or resources'],
                ['id' => 'events', 'name' => 'Events', 'description' => 'Event planning and coordination'],
                ['id' => 'general', 'name' => 'General', 'description' => 'General discussions']
            ];

            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;

        case 'get_stats':
            // Get collaboration statistics
            $stats = $supabaseClient->from('collaboration_posts')
                ->select('count, category', ['count' => 'exact'])
                ->execute();

            $totalPosts = $stats['count'] ?? 0;

            // Get category breakdown
            $categoryStats = [];
            foreach ($stats as $stat) {
                $category = $stat['category'] ?? 'general';
                if (!isset($categoryStats[$category])) {
                    $categoryStats[$category] = 0;
                }
                $categoryStats[$category]++;
            }

            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_posts' => $totalPosts,
                    'categories' => $categoryStats,
                    'active_users' => 1247, // This would be calculated from actual data
                    'posts_today' => 23 // This would be calculated from actual data
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log('Collaboration API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>