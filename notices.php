<?php
// notices.php - With debug information

require_once '../database/connect.php';
require_once 'notice_process.php';

// Get student ID from session
$student_id = 4;

// Initialize the notice process
$noticeProcess = new NoticeProcess($conn, $student_id);

// Get all data
$student = $noticeProcess->getStudentData();
$allNotices = $noticeProcess->getAllNotices();
$stats = $noticeProcess->getNoticeStats();
$categories = $noticeProcess->getCategoriesWithCounts();
$importantNotices = $noticeProcess->getImportantNotices();
$recentNotices = $noticeProcess->getRecentNotices(5);
$archiveNotices = $noticeProcess->getArchiveNotices(4);

// Debug - Check if notices exist
$hasNotices = !empty($allNotices) && count($allNotices) > 0;
$noticeCount = count($allNotices);

// Format student name
$studentName = htmlspecialchars($student['first_name'] ?? 'Ahmed') . ' ' . htmlspecialchars($student['last_name'] ?? 'Faraz');
$studentInitials = strtoupper(substr($student['first_name'] ?? 'A', 0, 1) . substr($student['last_name'] ?? 'F', 0, 1));
$studentClass = htmlspecialchars($student['class_name'] ?? 'Class 10');
$studentUID = htmlspecialchars($student['student_uid'] ?? 'STU-1024');

$totalNotices = $stats['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Notices — Crescent Public School Student Portal" />
  <title>Notices &middot; Student Portal &middot; Crescent Public School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml" />
  <style>
    /* ===== All styles from exams page ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #0a0e1a;
      color: #e8edf5;
      min-height: 100vh;
      display: flex;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: #141b2b;
    }

    ::-webkit-scrollbar-thumb {
      background: #2a3a5a;
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #3a4a6a;
    }

    .app-sidebar {
      width: 260px;
      min-height: 100vh;
      background: linear-gradient(180deg, #0d1225 0%, #0a0e1a 100%);
      border-right: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 100;
    }

    .sidebar-head {
      padding: 20px 20px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #e8edf5;
    }

    .sidebar-brand img {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: rgba(96, 165, 250, 0.15);
      padding: 6px;
    }

    .brand-text strong {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      letter-spacing: -0.3px;
    }

    .brand-text small {
      font-size: 0.6rem;
      opacity: 0.4;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .sidebar-close {
      display: none;
      color: #e8edf5;
      font-size: 1.2rem;
      cursor: pointer;
    }

    .student-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 20px;
      margin: 12px 16px 8px;
      background: linear-gradient(135deg, rgba(96, 165, 250, 0.08), rgba(96, 165, 250, 0.02));
      border-radius: 14px;
      border: 1px solid rgba(96, 165, 250, 0.06);
    }

    .avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #6366f1);
      color: #fff;
      font-weight: 700;
      font-size: 0.85rem;
      flex-shrink: 0;
    }

    .avatar-lg {
      width: 44px;
      height: 44px;
      font-size: 1rem;
    }

    .avatar.amber {
      background: linear-gradient(135deg, #f59e0b, #fbbf24);
    }

    .student-card-text {
      flex: 1;
      min-width: 0;
    }

    .student-card-text strong {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .student-card-text small {
      font-size: 0.65rem;
      opacity: 0.5;
      display: block;
    }

    .verify {
      color: #34d399;
      font-size: 0.85rem;
    }

    .sidebar-nav {
      flex: 1;
      padding: 8px 12px 20px;
      overflow-y: auto;
    }

    .nav-group {
      padding: 16px 12px 6px;
      font-size: 0.6rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      opacity: 0.3;
      font-weight: 600;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 9px 12px;
      border-radius: 10px;
      color: #7a8aa8;
      text-decoration: none;
      transition: all 0.2s;
      font-size: 0.85rem;
      font-weight: 500;
      position: relative;
    }

    .nav-item:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
    }

    .nav-item.active {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(99, 102, 241, 0.06));
      color: #60a5fa;
    }

    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 24px;
      background: linear-gradient(180deg, #3b82f6, #6366f1);
      border-radius: 0 4px 4px 0;
    }

    .nav-item i {
      font-size: 1.1rem;
      width: 20px;
      text-align: center;
      flex-shrink: 0;
    }

    .nav-item span {
      flex: 1;
    }

    .nav-tag {
      background: rgba(255, 255, 255, 0.06);
      padding: 1px 10px;
      border-radius: 12px;
      font-size: 0.6rem;
      font-weight: 600;
    }

    .nav-tag-info {
      background: rgba(96, 165, 250, 0.15);
      color: #60a5fa;
    }

    .nav-tag-warn {
      background: rgba(251, 191, 36, 0.15);
      color: #fbbf24;
    }

    .nav-tag-danger {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
    }

    .sidebar-foot {
      padding: 12px 20px 16px;
      border-top: 1px solid rgba(255, 255, 255, 0.04);
      margin-top: auto;
    }

    .logout-btn {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 9px 12px;
      border-radius: 10px;
      color: #f87171;
      text-decoration: none;
      transition: all 0.2s;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .logout-btn:hover {
      background: rgba(239, 68, 68, 0.08);
    }

    .copy {
      font-size: 0.6rem;
      opacity: 0.2;
      margin: 8px 0 0 12px;
      letter-spacing: 0.3px;
    }

    .app-main {
      flex: 1;
      min-height: 100vh;
      background: linear-gradient(180deg, #0d1225 0%, #0a0e1a 100%);
      display: flex;
      flex-direction: column;
    }

    .app-topbar {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 14px 28px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      background: rgba(13, 18, 37, 0.8);
      backdrop-filter: blur(12px);
      position: sticky;
      top: 0;
      z-index: 50;
      flex-wrap: wrap;
    }

    .nav-btn {
      display: none;
      font-size: 1.4rem;
      color: #e8edf5;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
    }

    .topbar-title {
      flex: 1;
      min-width: 120px;
    }

    .topbar-title h1 {
      font-size: 1.3rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: -0.5px;
      background: linear-gradient(135deg, #e8edf5, #94a3b8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .topbar-title .crumbs {
      font-size: 0.7rem;
      opacity: 0.35;
      margin-top: 2px;
      -webkit-text-fill-color: #94a3b8;
    }

    .topbar-title .crumbs a {
      color: #94a3b8;
      text-decoration: none;
      -webkit-text-fill-color: #94a3b8;
    }

    .topbar-title .crumbs a:hover {
      color: #e8edf5;
    }

    .topbar-title .crumbs span {
      margin: 0 4px;
    }

    .topbar-search {
      display: flex;
      align-items: center;
      background: rgba(255, 255, 255, 0.04);
      border-radius: 10px;
      padding: 6px 14px;
      border: 1px solid rgba(255, 255, 255, 0.04);
      transition: all 0.3s;
      min-width: 200px;
    }

    .topbar-search:focus-within {
      border-color: rgba(96, 165, 250, 0.3);
      background: rgba(255, 255, 255, 0.06);
    }

    .topbar-search i {
      opacity: 0.3;
      margin-right: 10px;
      font-size: 0.9rem;
    }

    .topbar-search input {
      background: transparent;
      border: none;
      color: #e8edf5;
      padding: 6px 0;
      outline: none;
      width: 100%;
      font-size: 0.85rem;
    }

    .topbar-search input::placeholder {
      color: #4a5a7a;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .icon-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 10px;
      color: #7a8aa8;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      background: transparent;
      cursor: pointer;
    }

    .icon-btn:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
    }

    .icon-btn .ping {
      position: absolute;
      top: 2px;
      right: 2px;
      background: #ef4444;
      color: #fff;
      font-size: 0.55rem;
      padding: 1px 6px;
      border-radius: 10px;
      min-width: 18px;
      text-align: center;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    .profile-chip {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 4px 14px 4px 4px;
      border-radius: 30px;
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
      border: 1px solid rgba(255, 255, 255, 0.04);
    }

    .profile-chip:hover {
      background: rgba(255, 255, 255, 0.08);
    }

    .profile-chip .who {
      line-height: 1.2;
    }

    .profile-chip .who b {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .profile-chip .who small {
      font-size: 0.6rem;
      opacity: 0.4;
    }

    .profile-chip .bi-chevron-down {
      font-size: 0.7rem;
      opacity: 0.3;
    }

    .icon-drop {
      position: relative;
    }

    .drop-panel {
      display: none;
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      min-width: 280px;
      background: #141b2b;
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: 6px 0;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
      z-index: 200;
      backdrop-filter: blur(20px);
    }

    .icon-drop:hover .drop-panel {
      display: block;
    }

    .drop-head {
      display: flex;
      justify-content: space-between;
      padding: 10px 16px 10px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      font-size: 0.85rem;
    }

    .drop-head a {
      color: #60a5fa;
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .drop-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 16px;
      color: #e8edf5;
      text-decoration: none;
      transition: background 0.2s;
      font-size: 0.85rem;
    }

    .drop-row:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .drop-row p {
      margin: 0;
    }

    .drop-row small {
      font-size: 0.65rem;
      opacity: 0.4;
      display: block;
    }

    .dot-ico {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.04);
      flex-shrink: 0;
    }

    .dot-ico.p-info {
      background: rgba(96, 165, 250, 0.12);
      color: #60a5fa;
    }

    .dot-ico.p-warn {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .dot-ico.p-danger {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .dot-ico.p-teal {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .dot-ico.p-violet {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .dot-ico.p-ok {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .app-content {
      padding: 24px 28px 20px;
      flex: 1;
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
    }

    .welcome {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.06), rgba(99, 102, 241, 0.03));
      border-radius: 20px;
      padding: 28px 32px;
      border: 1px solid rgba(255, 255, 255, 0.04);
      position: relative;
      overflow: hidden;
    }

    .welcome::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(59, 130, 246, 0.04), transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }

    .welcome .eyebrow {
      display: inline-block;
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      opacity: 0.35;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .welcome h2 {
      font-size: 1.6rem;
      font-weight: 700;
      margin: 0 0 6px;
      letter-spacing: -0.5px;
    }

    .welcome p {
      opacity: 0.6;
      font-size: 0.9rem;
      margin: 0;
      max-width: 600px;
    }

    .welcome p b {
      color: #60a5fa;
      opacity: 1;
    }

    .quick-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 14px;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.04);
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 500;
      transition: all 0.2s;
      border: 1px solid rgba(255, 255, 255, 0.04);
    }

    .chip:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #e8edf5;
      border-color: rgba(255, 255, 255, 0.08);
    }

    .chip i {
      font-size: 0.8rem;
    }

    .fact {
      background: rgba(255, 255, 255, 0.03);
      padding: 12px 14px;
      border-radius: 12px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.04);
      height: 100%;
    }

    .fact small {
      display: block;
      font-size: 0.6rem;
      opacity: 0.3;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }

    .fact strong {
      display: block;
      font-size: 1.1rem;
      font-weight: 700;
      margin: 4px 0 2px;
      color: #e8edf5;
    }

    .fact span {
      font-size: 0.7rem;
      opacity: 0.4;
    }

    .stat-card {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
      border-radius: 16px;
      padding: 20px 22px;
      border: 1px solid rgba(255, 255, 255, 0.04);
      transition: all 0.3s;
      height: 100%;
      position: relative;
      overflow: hidden;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
    }

    .stat-card .stat-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .stat-card .stat-ico {
      background: rgba(255, 255, 255, 0.04);
      padding: 8px;
      border-radius: 10px;
      color: #60a5fa;
      font-size: 1.1rem;
    }

    .stat-card .stat-trend {
      font-size: 0.7rem;
      opacity: 0.5;
      font-weight: 500;
    }

    .stat-card .stat-trend.flat {
      color: #fbbf24;
      opacity: 1;
    }

    .stat-card .stat-trend.down {
      color: #f87171;
      opacity: 1;
    }

    .stat-card .stat-num {
      font-size: 1.8rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: -1px;
      line-height: 1.2;
    }

    .stat-card .stat-title {
      font-size: 0.85rem;
      opacity: 0.6;
      margin: 2px 0 0;
      font-weight: 500;
    }

    .stat-card .stat-desc {
      font-size: 0.7rem;
      opacity: 0.3;
      margin: 2px 0 0;
    }

    .stat-card .stat-bar {
      display: block;
      height: 3px;
      background: rgba(255, 255, 255, 0.04);
      border-radius: 4px;
      margin-top: 12px;
      overflow: hidden;
    }

    .stat-card .stat-bar i {
      display: block;
      height: 100%;
      border-radius: 4px;
      transition: width 0.6s ease;
    }

    .stat-card.teal .stat-bar i {
      background: linear-gradient(90deg, #34d399, #6ee7b7);
    }

    .stat-card.info .stat-bar i {
      background: linear-gradient(90deg, #3b82f6, #6366f1);
    }

    .stat-card.ok .stat-bar i {
      background: linear-gradient(90deg, #34d399, #6ee7b7);
    }

    .stat-card.amber .stat-bar i {
      background: linear-gradient(90deg, #f59e0b, #fbbf24);
    }

    .stat-card.teal .stat-ico {
      color: #34d399;
    }

    .stat-card.info .stat-ico {
      color: #60a5fa;
    }

    .stat-card.ok .stat-ico {
      color: #34d399;
    }

    .stat-card.amber .stat-ico {
      color: #fbbf24;
    }

    .card {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.005));
      border: 1px solid rgba(255, 255, 255, 0.04);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.3s;
    }

    .card.lift:hover {
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
    }

    .card .card-head {
      padding: 18px 22px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
    }

    .card .card-head h3 {
      margin: 0;
      font-size: 1rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .card .card-head h3 .sub {
      font-size: 0.7rem;
      opacity: 0.4;
      font-weight: 400;
    }

    .card .card-body {
      padding: 20px 22px;
    }

    .card .card-body.tight {
      padding: 12px 16px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 12px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.2px;
    }

    .pill.p-grey {
      background: rgba(255, 255, 255, 0.04);
      color: #5a6a8a;
    }

    .pill.p-ok {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .pill.p-warn {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .pill.p-danger {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .pill.p-info {
      background: rgba(96, 165, 250, 0.12);
      color: #60a5fa;
    }

    .pill.p-teal {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .pill.p-violet {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .pill.bare {
      background: transparent;
      padding: 0 4px;
    }

    .meta-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .meta-list li {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      font-size: 0.85rem;
    }

    .meta-list li:last-child {
      border-bottom: none;
    }

    .meta-list li i {
      color: #60a5fa;
      margin-right: 10px;
      width: 18px;
      text-align: center;
      opacity: 0.6;
    }

    .meta-list li span {
      color: #5a6a8a;
      font-weight: 500;
    }

    .notice-row {
      display: flex;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      align-items: flex-start;
    }

    .notice-row:last-child {
      border-bottom: none;
    }

    .notice-row .notice-date {
      min-width: 40px;
      text-align: center;
      padding-top: 2px;
      flex-shrink: 0;
    }

    .notice-row .notice-date b {
      display: block;
      font-size: 1rem;
      font-weight: 700;
      color: #60a5fa;
    }

    .notice-row .notice-date small {
      font-size: 0.55rem;
      opacity: 0.3;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .notice-row h4 {
      font-size: 0.85rem;
      font-weight: 600;
      margin: 0 0 2px;
    }

    .notice-row p {
      font-size: 0.75rem;
      opacity: 0.4;
      margin: 0;
    }

    .notice-row.teal .notice-date b {
      color: #34d399;
    }

    .notice-row.amber .notice-date b {
      color: #fbbf24;
    }

    .notice-row.info .notice-date b {
      color: #60a5fa;
    }

    .notice-row.violet .notice-date b {
      color: #a78bfa;
    }

    .tag-list {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .divider-soft {
      height: 1px;
      background: rgba(255, 255, 255, 0.04);
      margin: 12px 0;
    }

    .btn-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.06);
      color: #94a3b8;
      text-decoration: none;
      transition: all 0.2s;
      background: transparent;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-outline:hover {
      background: rgba(255, 255, 255, 0.04);
      border-color: rgba(255, 255, 255, 0.12);
      color: #e8edf5;
    }

    .btn-solid {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      border-radius: 10px;
      background: linear-gradient(135deg, #3b82f6, #6366f1);
      color: #fff;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-solid:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    }

    .w-100 {
      width: 100%;
    }

    .justify-content-center {
      justify-content: center;
    }

    .d-grid {
      display: grid;
    }

    .gap-2 {
      gap: 8px;
    }

    .mt-3 {
      margin-top: 16px;
    }

    .mini-link {
      color: #60a5fa;
      text-decoration: none;
      font-size: 0.8rem;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-weight: 500;
      transition: color 0.2s;
    }

    .mini-link:hover {
      color: #93bbfc;
    }

    .mini-link.ghost {
      color: #5a6a8a;
    }

    .mini-link.ghost:hover {
      color: #94a3b8;
    }

    .mini-link.amber {
      color: #fbbf24;
    }

    .mini-link.amber:hover {
      color: #fcd34d;
    }

    .page-foot {
      padding: 16px 28px;
      border-top: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 0.75rem;
      opacity: 0.25;
      margin-top: auto;
    }

    .page-foot a {
      color: #94a3b8;
      text-decoration: none;
      transition: color 0.2s;
    }

    .page-foot a:hover {
      color: #e8edf5;
    }

    .page-foot .d-flex {
      display: flex;
      gap: 16px;
    }

    .nav-toggle {
      display: none;
    }

    .nav-backdrop {
      display: none;
    }

    @media (max-width: 992px) {
      .app-sidebar {
        position: fixed;
        left: -280px;
        top: 0;
        bottom: 0;
        width: 280px;
        z-index: 1000;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        border-right: none;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
      }

      .nav-toggle:checked~.app-sidebar {
        left: 0;
      }

      .nav-toggle:checked~.nav-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 999;
        backdrop-filter: blur(4px);
      }

      .sidebar-close {
        display: inline-flex;
      }

      .nav-btn {
        display: inline-flex;
      }

      .app-topbar {
        padding: 12px 16px;
      }

      .app-content {
        padding: 16px;
      }

      .welcome {
        padding: 20px;
      }

      .welcome h2 {
        font-size: 1.3rem;
      }

      .topbar-search {
        min-width: 120px;
      }

      .profile-chip .who {
        display: none;
      }

      .stat-card .stat-num {
        font-size: 1.5rem;
      }

      .card .card-head {
        padding: 14px 16px;
      }

      .card .card-body {
        padding: 14px 16px;
      }

      .page-foot {
        flex-direction: column;
        text-align: center;
        padding: 12px 16px;
      }

      .topbar-actions .icon-drop:not(:last-child) {
        display: none;
      }

      .topbar-title h1 {
        font-size: 1.1rem;
      }

      .col-md-6 {
        margin-bottom: 12px;
      }
    }

    @media (max-width: 576px) {
      .welcome .row .col-lg-8 {
        margin-bottom: 16px;
      }

      .fact {
        padding: 8px 10px;
      }

      .fact strong {
        font-size: 0.95rem;
      }

      .quick-chips .chip {
        font-size: 0.7rem;
        padding: 4px 10px;
      }

      .stat-card {
        padding: 14px 16px;
      }

      .stat-card .stat-num {
        font-size: 1.3rem;
      }

      .card .card-head h3 {
        font-size: 0.9rem;
      }

      .card .card-head h3 .sub {
        font-size: 0.6rem;
      }

      .notice-row h4 {
        font-size: 0.8rem;
      }

      .notice-row p {
        font-size: 0.7rem;
      }

      .pill {
        font-size: 0.6rem;
        padding: 1px 8px;
      }

      .tag-list .pill {
        font-size: 0.55rem;
      }
    }

    @media print {

      .app-sidebar,
      .app-topbar,
      .page-foot,
      .no-print {
        display: none !important;
      }

      .app-main {
        background: white !important;
        color: black !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      .app-content {
        padding: 20px !important;
        max-width: 100% !important;
      }

      .card {
        border: 1px solid #ddd !important;
        background: white !important;
        box-shadow: none !important;
        margin-bottom: 16px !important;
        page-break-inside: avoid !important;
      }

      .card .card-head {
        border-bottom: 2px solid #ddd !important;
        padding: 12px 16px !important;
      }

      .card .card-head h3 {
        color: #333 !important;
      }

      .card .card-head h3 .sub {
        color: #666 !important;
        opacity: 1 !important;
      }

      .card .card-body {
        padding: 16px !important;
      }

      .welcome {
        border: 1px solid #ddd !important;
        background: #f9f9f9 !important;
        padding: 20px !important;
        border-radius: 8px !important;
      }

      .welcome h2 {
        color: #333 !important;
      }

      .welcome p {
        color: #555 !important;
        opacity: 1 !important;
      }

      .welcome .eyebrow {
        color: #666 !important;
        opacity: 1 !important;
      }

      .fact {
        border: 1px solid #ddd !important;
        background: #f9f9f9 !important;
      }

      .fact strong {
        color: #333 !important;
      }

      .fact small {
        color: #666 !important;
        opacity: 1 !important;
      }

      .fact span {
        color: #666 !important;
        opacity: 1 !important;
      }

      .stat-card {
        border: 1px solid #ddd !important;
        background: #f9f9f9 !important;
      }

      .stat-card .stat-num {
        color: #333 !important;
      }

      .stat-card .stat-title {
        color: #555 !important;
        opacity: 1 !important;
      }

      .stat-card .stat-desc {
        color: #666 !important;
        opacity: 1 !important;
      }

      .pill {
        background: #eee !important;
        color: #333 !important;
        border: 1px solid #ddd !important;
      }

      .meta-list li {
        border-bottom: 1px solid #eee !important;
        color: #333 !important;
      }

      .meta-list li span {
        color: #666 !important;
      }

      .meta-list li i {
        color: #666 !important;
      }

      .notice-row {
        border-bottom: 1px solid #eee !important;
      }

      .notice-row h4 {
        color: #333 !important;
      }

      .notice-row p {
        color: #666 !important;
        opacity: 1 !important;
      }

      .notice-row .notice-date b {
        color: #2563eb !important;
      }

      .notice-row .notice-date small {
        color: #666 !important;
        opacity: 1 !important;
      }

      .tag-list .pill {
        border: 1px solid #ddd !important;
        background: #f5f5f5 !important;
        color: #333 !important;
      }
    }
  </style>
</head>

<body>

  <input type="checkbox" id="navToggle" class="nav-toggle" />
  <label for="navToggle" class="nav-backdrop" aria-hidden="true"></label>

  <!-- ===== SIDEBAR ===== -->
  <aside class="app-sidebar">
    <div class="sidebar-head">
      <a href="index.php" class="sidebar-brand">
        <img src="assets/images/logo.svg" alt="Crescent Public School logo" />
        <span class="brand-text"><strong>Crescent Public School</strong><small>Student Portal</small></span>
      </a>
      <label for="navToggle" class="sidebar-close" aria-label="Close navigation"><i class="bi bi-x-lg"></i></label>
    </div>

    <div class="student-card">
      <span class="avatar avatar-lg"><?php echo $studentInitials; ?></span>
      <div class="student-card-text">
        <strong><?php echo $studentName; ?></strong>
        <small><?php echo $studentClass . ' · ' . $studentUID; ?></small>
      </div>
      <span class="verify" title="Verified student"><i class="bi bi-patch-check-fill"></i></span>
    </div>

    <nav class="sidebar-nav">
      <p class="nav-group">Overview</p>
      <a class="nav-item" href="index.php"><i class="bi bi-columns-gap"></i><span>Dashboard</span></a>

      <p class="nav-group">Academics</p>
      <a class="nav-item" href="subjects.php"><i class="bi bi-journal-bookmark"></i><span>My Subjects</span></a>
      <a class="nav-item" href="timetable.php"><i class="bi bi-calendar-week"></i><span>My Timetable</span></a>
      <a class="nav-item" href="attendance.php"><i class="bi bi-check2-square"></i><span>My Attendance</span></a>
      <a class="nav-item" href="assignments.php"><i class="bi bi-journal-text"></i><span>Assignments</span></a>
      <a class="nav-item" href="exams.php"><i class="bi bi-pencil-square"></i><span>Exams</span></a>
      <a class="nav-item" href="results.php"><i class="bi bi-graph-up-arrow"></i><span>Results</span></a>

      <p class="nav-group">Finance</p>
      <a class="nav-item" href="fees.php"><i class="bi bi-wallet2"></i><span>Fees</span></a>

      <p class="nav-group">School Life</p>
      <a class="nav-item active" href="notices.php"><i class="bi bi-megaphone"></i><span>Notices</span><em
          class="nav-tag nav-tag-info"><?php echo $totalNotices; ?></em></a>
      <a class="nav-item" href="events.php"><i class="bi bi-calendar2-heart"></i><span>Events</span></a>
      <a class="nav-item" href="messages.php"><i class="bi bi-envelope"></i><span>Messages</span></a>

      <p class="nav-group">Account</p>
      <a class="nav-item" href="profile.php"><i class="bi bi-person-badge"></i><span>My Profile</span></a>
      <a class="nav-item" href="settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
    </nav>

    <div class="sidebar-foot">
      <a href="#" class="logout-btn"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
      <p class="copy">Portal v2.6 &middot; Session 2026&ndash;27</p>
    </div>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <div class="app-main">

    <!-- ===== TOPBAR ===== -->
    <header class="app-topbar">
      <label for="navToggle" class="nav-btn" aria-label="Open navigation"><i class="bi bi-list"></i></label>
      <div class="topbar-title">
        <h1>Notices</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>School Life<span>/</span>Notices</div>
      </div>
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="search" placeholder="Search notices…" aria-label="Search" />
      </div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span class="ping"><?php echo min($totalNotices, 9); ?></span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <?php if (!empty($allNotices)): ?>
              <?php foreach (array_slice($allNotices, 0, 3) as $notice): ?>
                <a href="notices.php" class="drop-row">
                  <i class="dot-ico <?php echo $notice['category_color']; ?>"><i
                      class="bi <?php echo $notice['category_icon']; ?>"></i></i>
                  <span>
                    <p>
                      <?php echo htmlspecialchars(substr($notice['title'], 0, 40)) . (strlen($notice['title']) > 40 ? '...' : ''); ?>
                    </p>
                    <small><?php echo $notice['time_ago'] ?? date('M d, Y', strtotime($notice['created_at'])); ?></small>
                  </span>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="drop-row"><span>
                  <p style="opacity:0.4;">No new notifications</p>
                </span></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="icon-drop">
          <a href="messages.php" class="icon-btn" aria-label="Messages"><i class="bi bi-envelope"></i></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Messages</strong><a href="messages.php">Open inbox</a></div>
            <div class="drop-row"><span>
                <p style="opacity:0.4;">No new messages</p>
              </span></div>
          </div>
        </div>
        <div class="icon-drop">
          <a href="profile.php" class="profile-chip">
            <span class="avatar"><?php echo $studentInitials; ?></span>
            <span
              class="who"><b><?php echo htmlspecialchars($student['first_name'] ?? 'Ahmed'); ?></b><small><?php echo $studentClass; ?></small></span>
            <i class="bi bi-chevron-down"></i>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong><?php echo $studentName; ?></strong><span class="pill p-ok">Active</span>
            </div>
            <a href="profile.php" class="drop-row"><i class="dot-ico p-teal"><i
                  class="bi bi-person-badge"></i></i><span>
                <p>My Profile</p><small><?php echo $studentUID; ?></small>
              </span></a>
            <a href="settings.php" class="drop-row"><i class="dot-ico p-violet"><i class="bi bi-gear"></i></i><span>
                <p>Settings</p><small>Preferences &amp; alerts</small>
              </span></a>
            <a href="#" class="drop-row"><i class="dot-ico p-danger"><i class="bi bi-box-arrow-right"></i></i><span>
                <p>Logout</p><small>End this session</small>
              </span></a>
          </div>
        </div>
      </div>
    </header>

    <!-- ===== CONTENT ===== -->
    <main class="app-content">

      <!-- ===== DEBUG INFO (Remove after testing) ===== -->
      <div
        style="background:rgba(255,255,255,0.05);padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:0.8rem;color:#94a3b8;border:1px solid rgba(255,255,255,0.05);">
        <i class="bi bi-info-circle"></i>
        <b>Debug:</b> Found <b><?php echo $noticeCount; ?></b> notices in database.
        <?php if ($noticeCount == 0): ?>
          <span style="color:#f87171;">No notices found! Please add sample data to the notices table.</span>
        <?php else: ?>
          <span style="color:#34d399;">Notices loaded successfully!</span>
        <?php endif; ?>
      </div>

      <!-- ===== WELCOME SECTION ===== -->
      <section class="welcome rise">
        <div class="row g-4 align-items-center">
          <div class="col-lg-8">
            <span class="eyebrow">Notice Board &middot; Updated daily</span>
            <h2><?php echo $totalNotices; ?> active notices from the school</h2>
            <p>Official announcements from the Principal Office, Examination Cell and Accounts. Notices marked
              <b>Important</b> require action from students and parents.</p>
            <div class="quick-chips">
              <a href="events.php" class="chip"><i class="bi bi-calendar2-heart"></i> Upcoming Events</a>
              <a href="exams.php" class="chip"><i class="bi bi-pencil-square"></i> Exam Notices</a>
              <a href="fees.php" class="chip"><i class="bi bi-wallet2"></i> Fee Notices</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact">
                  <small>Active Notices</small>
                  <strong><?php echo str_pad($totalNotices, 2, '0', STR_PAD_LEFT); ?></strong>
                  <span>This month</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Important</small>
                  <strong><?php echo str_pad($stats['important'] ?? 0, 2, '0', STR_PAD_LEFT); ?></strong>
                  <span>Action needed</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Categories</small>
                  <strong><?php echo count($categories); ?></strong>
                  <span>Academic to Event</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Latest</small>
                  <strong><?php echo !empty($allNotices) ? $allNotices[0]['time_ago'] ?? 'Today' : 'N/A'; ?></strong>
                  <span><?php echo !empty($allNotices) ? htmlspecialchars(substr($allNotices[0]['title'], 0, 15)) : 'No notices'; ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== STATS ===== -->
      <section class="row g-3 mt-4">
        <div class="col-md-6 col-xl-3 rise rise-1">
          <div class="stat-card teal">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-mortarboard"></i></span>
              <span
                class="stat-trend flat"><?php echo $stats['academic'] > 0 ? $stats['academic'] . ' new' : '0'; ?></span>
            </div>
            <h3 class="stat-num"><?php echo $stats['academic'] ?? 0; ?></h3>
            <p class="stat-title">Academic</p>
            <p class="stat-desc">Syllabus, timetable &amp; classes</p>
            <span class="stat-bar"><i
                style="width:<?php echo $totalNotices > 0 ? ($stats['academic'] / $totalNotices) * 100 : 0; ?>%"></i></span>
          </div>
        </div>
        <div class="col-md-6 col-xl-3 rise rise-2">
          <div class="stat-card info">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-pencil-square"></i></span>
              <span class="stat-trend down"><?php echo ($stats['administrative'] ?? 0) > 0 ? 'Action' : '0'; ?></span>
            </div>
            <h3 class="stat-num"><?php echo ($stats['administrative'] ?? 0) + ($stats['holiday'] ?? 0); ?></h3>
            <p class="stat-title">Admin / Holiday</p>
            <p class="stat-desc">Circulars &amp; announcements</p>
            <span class="stat-bar"><i
                style="width:<?php echo $totalNotices > 0 ? (($stats['administrative'] + $stats['holiday']) / $totalNotices) * 100 : 0; ?>%"></i></span>
          </div>
        </div>
        <div class="col-md-6 col-xl-3 rise rise-3">
          <div class="stat-card ok">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-calendar2-heart"></i></span>
              <span class="stat-trend"><?php echo $stats['event'] > 0 ? $stats['event'] . ' upcoming' : '0'; ?></span>
            </div>
            <h3 class="stat-num"><?php echo $stats['event'] ?? 0; ?></h3>
            <p class="stat-title">Event</p>
            <p class="stat-desc">Functions &amp; competitions</p>
            <span class="stat-bar"><i
                style="width:<?php echo $totalNotices > 0 ? ($stats['event'] / $totalNotices) * 100 : 0; ?>%"></i></span>
          </div>
        </div>
        <div class="col-md-6 col-xl-3 rise rise-4">
          <div class="stat-card amber">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-trophy"></i></span>
              <span class="stat-trend flat"><?php echo $stats['sports'] > 0 ? 'Active' : '0'; ?></span>
            </div>
            <h3 class="stat-num"><?php echo $stats['sports'] ?? 0; ?></h3>
            <p class="stat-title">Sports</p>
            <p class="stat-desc">Games &amp; athletics</p>
            <span class="stat-bar"><i
                style="width:<?php echo $totalNotices > 0 ? ($stats['sports'] / $totalNotices) * 100 : 0; ?>%"></i></span>
          </div>
        </div>
      </section>

      <!-- ===== NOTICES LIST ===== -->
      <section class="row g-3 mt-1 rise">
        <div class="col-xl-8">
          <div class="row g-3">

            <?php if (!empty($allNotices) && count($allNotices) > 0): ?>
              <?php foreach ($allNotices as $index => $notice): ?>
                <?php if ($index == 0): ?>
                  <!-- Featured/First Notice - Full width -->
                  <div class="col-12">
                    <div class="card lift h-100">
                      <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                          <span class="pill <?php echo $notice['category_color'] ?? 'p-grey'; ?>">
                            <i class="bi <?php echo $notice['category_icon'] ?? 'bi-info-circle'; ?>"></i>
                            <?php echo getCategoryLabel($notice['category'] ?? 'General'); ?>
                          </span>
                          <span style="font-size:.72rem;color:#5a6a8a;">
                            <i class="bi bi-clock"></i>
                            <?php echo $notice['formatted_date'] ?? date('d M Y', strtotime($notice['created_at'] ?? 'now')); ?>
                            &middot;
                            <?php echo $notice['formatted_time'] ?? date('h:i A', strtotime($notice['created_at'] ?? 'now')); ?>
                          </span>
                        </div>
                        <h3 style="font-size:1.1rem"><?php echo htmlspecialchars($notice['title'] ?? 'Untitled Notice'); ?>
                        </h3>
                        <p style="font-size:.84rem;color:#5a6a8a;line-height:1.6">
                          <?php echo htmlspecialchars($notice['details'] ?? 'No details available.'); ?></p>
                        <div class="tag-list mb-3">
                          <span class="pill p-grey bare"><?php echo $studentClass; ?></span>
                          <span
                            class="pill p-grey bare"><?php echo htmlspecialchars($notice['posted_by'] ?? 'School Office'); ?></span>
                          <?php if (isImportant($notice['title'] ?? '')): ?>
                            <span class="pill p-danger">Important</span>
                          <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                          <span style="font-size:.75rem;color:#5a6a8a;">
                            <i class="bi bi-person-badge me-1"></i>Posted by
                            <?php echo htmlspecialchars($notice['posted_by'] ?? 'School Office'); ?>
                          </span>
                          <span class="d-flex gap-2">
                            <a href="#" class="mini-link">View Details <i class="bi bi-arrow-right"></i></a>
                            <a href="#" class="mini-link ghost">Download PDF</a>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php else: ?>
                  <!-- Other notices - 2 columns -->
                  <div class="col-md-6">
                    <div class="card lift h-100">
                      <div class="card-body">
                        <span class="pill <?php echo $notice['category_color'] ?? 'p-grey'; ?>">
                          <i class="bi <?php echo $notice['category_icon'] ?? 'bi-info-circle'; ?>"></i>
                          <?php echo getCategoryLabel($notice['category'] ?? 'General'); ?>
                        </span>
                        <h3 class="mt-2" style="font-size:1rem">
                          <?php echo htmlspecialchars($notice['title'] ?? 'Untitled Notice'); ?></h3>
                        <p style="font-size:.82rem;color:#5a6a8a;line-height:1.55">
                          <?php echo htmlspecialchars(substr($notice['details'] ?? 'No details available.', 0, 120)) . (strlen($notice['details'] ?? '') > 120 ? '...' : ''); ?>
                        </p>
                        <?php if (isImportant($notice['title'] ?? '')): ?>
                          <span class="pill p-danger mb-2">Important</span>
                        <?php endif; ?>
                        <div class="divider-soft"></div>
                        <div class="d-flex justify-content-between align-items-center">
                          <span style="font-size:.72rem;color:#5a6a8a;">
                            <?php echo $notice['formatted_date'] ?? date('d M Y', strtotime($notice['created_at'] ?? 'now')); ?>
                            &middot; <?php echo htmlspecialchars($notice['posted_by'] ?? 'School Office'); ?>
                          </span>
                          <a href="#" class="mini-link">View <i class="bi bi-arrow-right"></i></a>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- No Notices Found -->
              <div class="col-12">
                <div class="card lift">
                  <div class="card-body" style="text-align:center;padding:60px 20px;">
                    <i class="bi bi-inbox" style="font-size:3rem;opacity:0.2;display:block;margin-bottom:16px;"></i>
                    <h3 style="font-size:1.2rem;opacity:0.5;">No notices available</h3>
                    <p style="opacity:0.3;max-width:400px;margin:0 auto;">
                      <?php if ($noticeCount == 0): ?>
                        The notices table is empty. Please add sample data to display notices.
                      <?php else: ?>
                        Check back later for updates from the school.
                      <?php endif; ?>
                    </p>
                    <?php if ($noticeCount == 0): ?>
                      <div
                        style="margin-top:16px;padding:12px 20px;background:rgba(251,191,36,0.08);border-radius:10px;border:1px solid rgba(251,191,36,0.15);display:inline-block;font-size:0.8rem;color:#fbbf24;">
                        <i class="bi bi-lightbulb"></i> Run the SQL query to insert sample notices
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- ===== SIDEBAR - Filters & Archive ===== -->
        <div class="col-xl-4">
          <!-- Filter by Category -->
          <div class="card lift mb-3">
            <div class="card-head">
              <h3>Filter by Category <span class="sub">Notice board</span></h3>
            </div>
            <div class="card-body">
              <ul class="meta-list">
                <?php if (!empty($categories)): ?>
                  <?php foreach ($categories as $category): ?>
                    <li>
                      <i class="bi <?php echo getCategoryIcon($category['category']); ?>"></i>
                      <?php echo getCategoryLabel($category['category']); ?>
                      <span><?php echo $category['count']; ?></span>
                    </li>
                  <?php endforeach; ?>
                <?php else: ?>
                  <li style="opacity:0.4;">No categories available</li>
                <?php endif; ?>
              </ul>
              <div class="divider-soft"></div>
              <div class="d-flex flex-wrap gap-2">
                <a href="notices.php" class="mini-link">All Notices</a>
                <a href="notices.php" class="mini-link amber">Important</a>
                <a href="notices.php" class="mini-link ghost">Archived</a>
              </div>
            </div>
          </div>

          <!-- Notice Archive -->
          <?php if (!empty($archiveNotices)): ?>
            <div class="card lift mb-3">
              <div class="card-head">
                <h3>Notice Archive <span class="sub">Earlier this session</span></h3>
              </div>
              <div class="card-body tight">
                <?php foreach ($archiveNotices as $archive): ?>
                  <div class="notice-row <?php echo strtolower($archive['category'] ?? 'general'); ?>">
                    <div class="notice-date">
                      <b><?php echo date('d', strtotime($archive['created_at'] ?? 'now')); ?></b>
                      <small><?php echo date('M', strtotime($archive['created_at'] ?? 'now')); ?></small>
                    </div>
                    <div>
                      <h4><?php echo htmlspecialchars($archive['title'] ?? 'Untitled'); ?></h4>
                      <p><?php echo getCategoryLabel($archive['category'] ?? 'General'); ?> &middot;
                        <?php echo htmlspecialchars($archive['posted_by'] ?? 'School Office'); ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Need Help -->
          <div class="card lift">
            <div class="card-head">
              <h3>Need Clarification? <span class="sub">Talk to the office</span></h3>
            </div>
            <div class="card-body">
              <p style="font-size:.82rem;color:#5a6a8a;line-height:1.6">Questions about any notice can be sent directly
                to the concerned department through the student messaging system.</p>
              <a href="messages.php" class="btn-solid w-100 justify-content-center"><i class="bi bi-envelope"></i>
                Message the Office</a>
            </div>
          </div>
        </div>
      </section>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="page-foot">
      <span>&copy; 2026 Crescent Public School &middot; Student Portal</span>
      <span class="d-flex gap-3">
        <a href="notices.php">Help Centre</a>
        <a href="messages.php">Contact Office</a>
        <a href="settings.php">Privacy</a>
      </span>
    </footer>
  </div>

</body>

</html>