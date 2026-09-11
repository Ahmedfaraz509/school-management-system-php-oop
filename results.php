<?php
// results.php - Fully dynamic results page with same design as exams

require_once '../database/connect.php';
require_once 'result_process.php';

// Get student ID from session (for demo, using student_id = 4)
$student_id = 4;

// Initialize the result process
$resultProcess = new ResultProcess($conn, $student_id);

// Get all data
$student = $resultProcess->getStudentData();
$results = $resultProcess->getStudentResults();
$stats = $resultProcess->getResultStats();
$subjectAverages = $resultProcess->getSubjectAverages();
$classRank = $resultProcess->getClassRank();
$notices = $resultProcess->getExamNotices(3);
$hasResults = $resultProcess->hasResults();

// Format student name for display
$studentName = htmlspecialchars($student['first_name'] ?? 'Ahmed') . ' ' . htmlspecialchars($student['last_name'] ?? 'Faraz');
$studentInitials = strtoupper(substr($student['first_name'] ?? 'A', 0, 1) . substr($student['last_name'] ?? 'F', 0, 1));
$studentClass = htmlspecialchars($student['class_name'] ?? 'Class 10');
$studentUID = htmlspecialchars($student['student_uid'] ?? 'STU-1024');

// Calculate overall grade
$overallGrade = getGrade($stats['average_percentage'] ?? 0);
$gradeColor = getGradeColor($overallGrade['grade']);

// Get subject count
$subjectCount = count($subjectAverages);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Results — Crescent Public School Student Portal" />
  <title>Results &middot; Student Portal &middot; Crescent Public School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml" />
  <style>
    /* ===== BASE STYLES - Same as exams page ===== */
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

    /* ===== SIDEBAR - Same as exams page ===== */
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

    .avatar.violet {
      background: linear-gradient(135deg, #8b5cf6, #a78bfa);
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

    /* ===== MAIN CONTENT ===== */
    .app-main {
      flex: 1;
      min-height: 100vh;
      background: linear-gradient(180deg, #0d1225 0%, #0a0e1a 100%);
      display: flex;
      flex-direction: column;
    }

    /* ===== TOPBAR - Same as exams page ===== */
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

    /* ===== CONTENT AREA ===== */
    .app-content {
      padding: 24px 28px 20px;
      flex: 1;
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
    }

    /* ===== WELCOME - Same as exams page ===== */
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

    /* ===== STAT CARDS - Same as exams page ===== */
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

    .stat-card .stat-num small {
      font-size: 1rem;
      opacity: 0.5;
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

    .stat-card.violet .stat-bar i {
      background: linear-gradient(90deg, #8b5cf6, #a78bfa);
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

    .stat-card.violet .stat-ico {
      color: #a78bfa;
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

    /* ===== CARDS - Same as exams page ===== */
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

    /* ===== TABLE - Same as exams page ===== */
    .table-responsive {
      overflow-x: auto;
    }

    .table {
      color: #e8edf5;
      margin: 0;
      font-size: 0.85rem;
      width: 100%;
      border-collapse: collapse;
    }

    .table thead th {
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      color: #5a6a8a;
      font-weight: 600;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 12px 16px;
      text-align: left;
    }

    .table tbody td {
      padding: 12px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.03);
      vertical-align: middle;
    }

    .table tbody tr:last-child td {
      border-bottom: none;
    }

    .table tbody tr:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .t-strong {
      font-weight: 600;
    }

    .t-sub {
      display: block;
      font-weight: 400;
      font-size: 0.7rem;
      opacity: 0.4;
      margin-top: 2px;
    }

    /* ===== SUBJECT CHIPS - Same as exams page ===== */
    .subject-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
    }

    .subject-chip i {
      font-size: 0.75rem;
    }

    .s-math {
      background: rgba(59, 130, 246, 0.12);
      color: #60a5fa;
    }

    .s-phy {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .s-cs {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .s-chem {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .s-eng {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .s-urdu {
      background: rgba(251, 191, 36, 0.08);
      color: #fbbf24;
    }

    .s-bio {
      background: rgba(52, 211, 153, 0.08);
      color: #34d399;
    }

    .s-econ {
      background: rgba(251, 191, 36, 0.08);
      color: #fbbf24;
    }

    .s-acc {
      background: rgba(59, 130, 246, 0.08);
      color: #60a5fa;
    }

    .s-bus {
      background: rgba(167, 139, 250, 0.08);
      color: #a78bfa;
    }

    /* ===== PILLS - Same as exams page ===== */
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

    .pill.bare {
      background: transparent;
      padding: 0 4px;
    }

    /* ===== META LIST - Same as exams page ===== */
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

    /* ===== ALERTS - Same as exams page ===== */
    .alert-soft {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 18px;
      border-radius: 12px;
      background: rgba(239, 68, 68, 0.06);
      border-left: 3px solid #f87171;
      font-size: 0.85rem;
    }

    .alert-soft i {
      color: #f87171;
      font-size: 1.1rem;
      margin-top: 2px;
      flex-shrink: 0;
    }

    .alert-soft.teal {
      background: rgba(52, 211, 153, 0.06);
      border-left-color: #34d399;
    }

    .alert-soft.teal i {
      color: #34d399;
    }

    .alert-soft.amber {
      background: rgba(251, 191, 36, 0.06);
      border-left-color: #fbbf24;
    }

    .alert-soft.amber i {
      color: #fbbf24;
    }

    /* ===== BARS FOR SUBJECT PERFORMANCE ===== */
    .bar-label {
      display: flex;
      justify-content: space-between;
      font-size: 0.85rem;
      margin-bottom: 4px;
    }

    .bar-label span b {
      font-weight: 600;
    }

    .bar {
      display: block;
      height: 6px;
      background: rgba(255, 255, 255, 0.04);
      border-radius: 4px;
      overflow: hidden;
      margin-bottom: 12px;
    }

    .bar:last-child {
      margin-bottom: 0;
    }

    .bar i {
      display: block;
      height: 100%;
      border-radius: 4px;
      transition: width 0.6s ease;
    }

    .divider-soft {
      height: 1px;
      background: rgba(255, 255, 255, 0.04);
      margin: 16px 0;
    }

    /* ===== BUTTONS - Same as exams page ===== */
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

    .rounded-3 {
      border-radius: 12px;
    }

    .p-3 {
      padding: 16px;
    }

    .text-center {
      text-align: center;
    }

    /* ===== FOOTER - Same as exams page ===== */
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

    /* ===== RESPONSIVE - Same as exams page ===== */
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

      .col-6.col-xl-3 {
        margin-bottom: 12px;
      }

      .col-xl-8 {
        margin-bottom: 16px;
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

      .table-responsive {
        font-size: 0.75rem;
      }

      .table thead th,
      .table tbody td {
        padding: 8px 10px;
      }

      .subject-chip {
        font-size: 0.7rem;
        padding: 2px 8px;
      }

      .pill {
        font-size: 0.6rem;
        padding: 1px 8px;
      }

      .card .card-head h3 {
        font-size: 0.9rem;
      }

      .card .card-head h3 .sub {
        font-size: 0.6rem;
      }
    }

    /* ===== PRINT STYLES ===== */
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

      .table {
        color: #333 !important;
        font-size: 0.8rem !important;
      }

      .table thead th {
        color: #333 !important;
        border-bottom: 2px solid #ddd !important;
        background: #f5f5f5 !important;
      }

      .table tbody td {
        color: #333 !important;
        border-bottom: 1px solid #eee !important;
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

      .subject-chip {
        background: #eee !important;
        color: #333 !important;
        border: 1px solid #ddd !important;
      }

      .subject-chip i {
        display: none !important;
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

      .alert-soft {
        border-left-color: #666 !important;
        background: #f5f5f5 !important;
        color: #333 !important;
      }

      .alert-soft i {
        color: #666 !important;
      }

      .bar {
        background: #eee !important;
      }

      .bar i {
        background: #666 !important;
      }

      .bar-label {
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
      <a class="nav-item" href="subjects.php"><i class="bi bi-journal-bookmark"></i><span>My Subjects</span><em
          class="nav-tag"><?php echo $subjectCount; ?></em></a>
      <a class="nav-item" href="timetable.php"><i class="bi bi-calendar-week"></i><span>My Timetable</span></a>
      <a class="nav-item" href="attendance.php"><i class="bi bi-check2-square"></i><span>My Attendance</span></a>
      <a class="nav-item" href="assignments.php"><i class="bi bi-journal-text"></i><span>Assignments</span></a>
      <a class="nav-item" href="exams.php"><i class="bi bi-pencil-square"></i><span>Exams</span><em
          class="nav-tag nav-tag-info"><?php echo $stats['total_exams'] ?? 0; ?></em></a>
      <a class="nav-item active" href="results.php"><i class="bi bi-graph-up-arrow"></i><span>Results</span></a>

      <p class="nav-group">Finance</p>
      <a class="nav-item" href="fees.php"><i class="bi bi-wallet2"></i><span>Fees</span></a>

      <p class="nav-group">School Life</p>
      <a class="nav-item" href="notices.php"><i class="bi bi-megaphone"></i><span>Notices</span></a>
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
        <h1>Results</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>Academics<span>/</span>Results</div>
      </div>
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="search" placeholder="Search exams or subjects…" aria-label="Search" />
      </div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span class="ping"><?php echo count($notices); ?></span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <?php if (!empty($notices)): ?>
              <?php foreach ($notices as $notice): ?>
                <a href="notices.php" class="drop-row">
                  <i class="dot-ico p-info"><i class="bi bi-megaphone"></i></i>
                  <span>
                    <p>
                      <?php echo htmlspecialchars(substr($notice['title'], 0, 40)) . (strlen($notice['title']) > 40 ? '...' : ''); ?>
                    </p>
                    <small><?php echo date('M d, Y', strtotime($notice['created_at'])); ?></small>
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

      <!-- No Results Alert -->
      <?php if (!$hasResults): ?>
        <div class="alert-soft amber mb-3">
          <i class="bi bi-info-circle"></i>
          <span><b>No results available yet.</b> Results will appear here once they are published by the examination
            cell.</span>
        </div>
      <?php endif; ?>

      <!-- ===== WELCOME SECTION ===== -->
      <section class="welcome rise">
        <div class="row g-4 align-items-center">
          <div class="col-lg-8">
            <span
              class="eyebrow"><?php echo $hasResults ? 'Term 1 · Assessment Report' : 'No Results Available'; ?></span>
            <h2>
              <?php if ($hasResults && $stats['average_percentage'] > 0): ?>
                Grade <?php echo $overallGrade['grade']; ?> average — ranked <?php echo $classRank['rank']; ?>th in
                <?php echo $studentClass; ?>
              <?php elseif ($hasResults): ?>
                Results are being processed
              <?php else: ?>
                No results have been published yet
              <?php endif; ?>
            </h2>
            <p>
              <?php if ($hasResults && $stats['average_percentage'] > 0): ?>
                You cleared <?php echo $stats['passed_exams']; ?> out of <?php echo $stats['total_exams']; ?> assessments
                this term with an overall GPA of <?php echo number_format($stats['gpa'], 1); ?> out of 4.0.
                <b><?php echo $stats['best_subject']; ?></b> remains your strongest subject, while
                <b><?php echo $stats['worst_subject']; ?></b> needs focused attention.
              <?php elseif ($hasResults): ?>
                Results are currently being processed. Check back soon for your complete assessment report.
              <?php else: ?>
                No results have been published for your class yet. Please check back later or contact the examination cell
                for updates.
              <?php endif; ?>
            </p>
            <div class="quick-chips">
              <a href="exams.php" class="chip"><i class="bi bi-pencil-square"></i> Upcoming Exams</a>
              <a href="attendance.php" class="chip"><i class="bi bi-check2-square"></i> Attendance</a>
              <a href="messages.php" class="chip"><i class="bi bi-chat-dots"></i> Discuss with Teacher</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact">
                  <small>Overall GPA</small>
                  <strong><?php echo $hasResults ? number_format($stats['gpa'], 1) : 'N/A'; ?></strong>
                  <span>out of 4.0</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Average</small>
                  <strong><?php echo $hasResults ? number_format($stats['average_percentage'], 1) . '%' : 'N/A'; ?></strong>
                  <span>Grade <?php echo $overallGrade['grade']; ?></span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Class Rank</small>
                  <strong><?php echo $hasResults ? $classRank['rank'] . ' / ' . $classRank['total'] : 'N/A'; ?></strong>
                  <span>Top
                    <?php echo $classRank['total'] > 0 ? round(($classRank['rank'] / $classRank['total']) * 100) : 0; ?>%</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Passed</small>
                  <strong><?php echo $hasResults ? $stats['passed_exams'] . ' / ' . $stats['total_exams'] : 'N/A'; ?></strong>
                  <span><?php echo $hasResults && $stats['failed_exams'] == 0 ? 'All passed' : ($hasResults ? $stats['failed_exams'] . ' failed' : 'No exams'); ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== STATS ===== -->
      <section class="row g-3 mt-4">
        <div class="col-6 col-xl-3 rise rise-1">
          <div class="stat-card violet">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-award"></i></span>
              <span
                class="stat-trend"><?php echo $hasResults ? '+' . number_format($stats['gpa'] - 3.0, 1) : 'N/A'; ?></span>
            </div>
            <h3 class="stat-num"><?php echo $hasResults ? number_format($stats['gpa'], 1) : '—'; ?></h3>
            <p class="stat-title">Overall GPA</p>
            <p class="stat-desc"><?php echo $hasResults ? 'Grade ' . $overallGrade['grade'] . ' average' : 'No data'; ?>
            </p>
            <span class="stat-bar"><i
                style="width:<?php echo $hasResults ? ($stats['gpa'] / 4) * 100 : 0; ?>%"></i></span>
          </div>
        </div>
        <div class="col-6 col-xl-3 rise rise-2">
          <div class="stat-card teal">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-percent"></i></span>
              <span
                class="stat-trend"><?php echo $hasResults ? '+' . number_format($stats['average_percentage'] - 75, 1) . '%' : 'N/A'; ?></span>
            </div>
            <h3 class="stat-num">
              <?php echo $hasResults ? number_format($stats['average_percentage'], 1) . '<small>%</small>' : '—'; ?>
            </h3>
            <p class="stat-title">Average Percentage</p>
            <p class="stat-desc"><?php echo $hasResults ? 'Across all subjects' : 'No data'; ?></p>
            <span class="stat-bar"><i
                style="width:<?php echo $hasResults ? $stats['average_percentage'] : 0; ?>%"></i></span>
          </div>
        </div>
        <div class="col-6 col-xl-3 rise rise-3">
          <div class="stat-card info">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-clipboard-data"></i></span>
              <span class="stat-trend flat"><?php echo $hasResults ? 'Term 1' : 'No data'; ?></span>
            </div>
            <h3 class="stat-num"><?php echo $hasResults ? $stats['total_exams'] : '0'; ?></h3>
            <p class="stat-title">Total Exams</p>
            <p class="stat-desc"><?php echo $hasResults ? 'Tests, quizzes & practicals' : 'No exams completed'; ?></p>
            <span class="stat-bar"><i style="width:100%"></i></span>
          </div>
        </div>
        <div class="col-6 col-xl-3 rise rise-4">
          <div class="stat-card ok">
            <div class="stat-top">
              <span class="stat-ico"><i class="bi bi-check2-circle"></i></span>
              <span
                class="stat-trend"><?php echo $hasResults ? round(($stats['passed_exams'] / max(1, $stats['total_exams'])) * 100) . '%' : '0%'; ?></span>
            </div>
            <h3 class="stat-num"><?php echo $hasResults ? $stats['passed_exams'] : '0'; ?></h3>
            <p class="stat-title">Passed Exams</p>
            <p class="stat-desc">
              <?php echo $hasResults && $stats['failed_exams'] == 0 ? 'All passed' : ($hasResults ? $stats['failed_exams'] . ' failed' : 'No exams'); ?>
            </p>
            <span class="stat-bar"><i
                style="width:<?php echo $hasResults ? ($stats['passed_exams'] / max(1, $stats['total_exams'])) * 100 : 0; ?>%"></i></span>
          </div>
        </div>
      </section>

      <!-- ===== RESULTS TABLE ===== -->
      <section class="row g-3 mt-1">
        <div class="col-xl-8 rise">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Assessment Record <span
                  class="sub"><?php echo $hasResults ? 'Term 1, session 2026–27' : 'No results available'; ?></span>
              </h3>
              <span class="pill p-teal"><i class="bi bi-funnel"></i> All subjects</span>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Exam</th>
                    <th>Subject</th>
                    <th>Marks</th>
                    <th>Total</th>
                    <th>%</th>
                    <th>Grade</th>
                    <th>Result</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($hasResults && !empty($results)): ?>
                    <?php foreach ($results as $result): ?>
                      <tr>
                        <td class="t-strong">
                          <?php echo htmlspecialchars($result['exam_title']); ?>
                          <span class="t-sub"><?php echo date('M d, Y', strtotime($result['exam_date'])); ?></span>
                        </td>
                        <td>
                          <span class="subject-chip <?php echo getSubjectColor($result['subject_title']); ?>">
                            <i class="bi <?php echo getSubjectIcon($result['subject_title']); ?>"></i>
                            <?php echo htmlspecialchars($result['subject_title']); ?>
                          </span>
                        </td>
                        <td><?php echo $result['marks_obtained']; ?></td>
                        <td><?php echo $result['total_marks']; ?></td>
                        <td><?php echo number_format($result['percentage'], 1); ?>%</td>
                        <td><span
                            class="pill <?php echo $result['grade_color']; ?> bare"><?php echo $result['grade']; ?></span>
                        </td>
                        <td><span
                            class="pill <?php echo $result['percentage'] >= 40 ? 'p-ok' : 'p-danger'; ?>"><?php echo $result['percentage'] >= 40 ? 'Pass' : 'Fail'; ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" style="text-align:center;padding:40px;opacity:0.4;">
                        <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        No results have been published yet.
                        <br /><small style="font-size:0.8rem;">Results will appear here once they are available.</small>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($hasResults && !empty($results)): ?>
              <div class="card-body">
                <div class="row g-3 text-center">
                  <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:rgba(96,165,250,0.06);">
                      <b style="font-size:1.3rem;color:#60a5fa;display:block"><?php echo $stats['obtained_marks']; ?></b>
                      <small style="font-size:.7rem;color:#5a6a8a;font-weight:700;letter-spacing:.08em">MARKS
                        OBTAINED</small>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);">
                      <b style="font-size:1.3rem;color:#e8edf5;display:block"><?php echo $stats['total_marks']; ?></b>
                      <small style="font-size:.7rem;color:#5a6a8a;font-weight:700;letter-spacing:.08em">TOTAL
                        MARKS</small>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:rgba(52,211,153,0.06);">
                      <b
                        style="font-size:1.3rem;color:#34d399;display:block"><?php echo number_format($stats['average_percentage'], 1); ?>%</b>
                      <small style="font-size:.7rem;color:#5a6a8a;font-weight:700;letter-spacing:.08em">AGGREGATE</small>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background:rgba(167,139,250,0.06);">
                      <b style="font-size:1.3rem;color:#a78bfa;display:block"><?php echo $overallGrade['grade']; ?></b>
                      <small style="font-size:.7rem;color:#5a6a8a;font-weight:700;letter-spacing:.08em">OVERALL
                        GRADE</small>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ===== RESULT SUMMARY ===== -->
        <div class="col-xl-4 rise rise-2">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Result Summary <span
                  class="sub"><?php echo $hasResults ? 'Official term report' : 'No data available'; ?></span></h3>
            </div>
            <div class="card-body">
              <div class="text-center mb-3">
                <span class="avatar avatar-lg violet"><?php echo $studentInitials; ?></span>
                <h3 class="mt-3" style="font-size:1.1rem"><?php echo $studentName; ?></h3>
                <p class="mb-0" style="font-size:.78rem;color:#5a6a8a;"><?php echo $studentClass; ?> · Roll No.
                  <?php echo $studentUID; ?></p>
              </div>
              <ul class="meta-list">
                <li><i class="bi bi-calendar-check"></i> Term <span>Term 1, 2026–27</span></li>
                <li><i class="bi bi-trophy"></i> Class Position
                  <span><?php echo $hasResults ? $classRank['rank'] . ' of ' . $classRank['total'] : 'N/A'; ?></span>
                </li>
                <li><i class="bi bi-graph-up-arrow"></i> GPA
                  <span><?php echo $hasResults ? number_format($stats['gpa'], 1) . ' / 4.0' : 'N/A'; ?></span></li>
                <li><i class="bi bi-star"></i> Best Subject
                  <span><?php echo $hasResults ? $stats['best_subject'] : 'N/A'; ?></span></li>
                <li><i class="bi bi-exclamation-triangle"></i> Needs Focus
                  <span><?php echo $hasResults ? $stats['worst_subject'] : 'N/A'; ?></span></li>
                <li><i class="bi bi-check2-all"></i> Failed Subjects
                  <span><?php echo $hasResults ? ($stats['failed_exams'] > 0 ? $stats['failed_exams'] . ' subject(s)' : 'None') : 'N/A'; ?></span>
                </li>
              </ul>
              <div class="divider-soft"></div>
              <div class="alert-soft teal">
                <i class="bi bi-quote"></i>
                <span><b>Class teacher:</b>
                  “<?php echo $hasResults && $stats['average_percentage'] > 80 ? 'An excellent term. Keep up the great work!' : ($hasResults ? 'Improve your weaker subjects and you\'ll do even better next term.' : 'Results will be shared once available.'); ?>”</span>
              </div>
              <div class="d-grid gap-2 mt-3">
                <button onclick="window.print()" class="btn-solid justify-content-center"><i class="bi bi-printer"></i>
                  Print / Save Report</button>
                <a href="exams.php" class="btn-outline justify-content-center"><i class="bi bi-calendar-event"></i>
                  Upcoming Exams</a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== SUBJECT PERFORMANCE ===== -->
      <?php if ($hasResults && !empty($subjectAverages)): ?>
        <section class="row g-3 mt-1 rise">
          <div class="col-xl-6">
            <div class="card lift h-100">
              <div class="card-head">
                <h3>Subject Performance <span class="sub">Percentage scored per subject</span></h3>
              </div>
              <div class="card-body">
                <?php foreach ($subjectAverages as $subject): ?>
                  <div class="bar-label">
                    <span><b><?php echo htmlspecialchars($subject['subject_title']); ?></b></span>
                    <b><?php echo number_format($subject['avg_percentage'], 1); ?>%</b>
                  </div>
                  <span class="bar mb-3">
                    <i
                      style="width:<?php echo $subject['avg_percentage']; ?>%;background:<?php echo ResultProcess::getBarColor($subject['avg_percentage']); ?>"></i>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- ===== GRADING SCALE ===== -->
          <div class="col-xl-6">
            <div class="card lift h-100">
              <div class="card-head">
                <h3>Grading Scale <span class="sub">Session 2026–27</span></h3>
              </div>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Marks Range</th>
                      <th>Grade</th>
                      <th>Grade Point</th>
                      <th>Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="t-strong">90 – 100</td>
                      <td><span class="pill p-ok bare">A+</span></td>
                      <td>4.0</td>
                      <td>Outstanding</td>
                    </tr>
                    <tr>
                      <td class="t-strong">80 – 89</td>
                      <td><span class="pill p-ok bare">A</span></td>
                      <td>3.7</td>
                      <td>Excellent</td>
                    </tr>
                    <tr>
                      <td class="t-strong">70 – 79</td>
                      <td><span class="pill p-teal bare">B+</span></td>
                      <td>3.3</td>
                      <td>Very good</td>
                    </tr>
                    <tr>
                      <td class="t-strong">60 – 69</td>
                      <td><span class="pill p-teal bare">B</span></td>
                      <td>3.0</td>
                      <td>Good</td>
                    </tr>
                    <tr>
                      <td class="t-strong">50 – 59</td>
                      <td><span class="pill p-warn bare">C</span></td>
                      <td>2.0</td>
                      <td>Satisfactory</td>
                    </tr>
                    <tr>
                      <td class="t-strong">40 – 49</td>
                      <td><span class="pill p-warn bare">D</span></td>
                      <td>1.0</td>
                      <td>Needs improvement</td>
                    </tr>
                    <tr>
                      <td class="t-strong">Below 40</td>
                      <td><span class="pill p-danger bare">F</span></td>
                      <td>0.0</td>
                      <td>Fail</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="card-body">
                <div class="alert-soft amber">
                  <i class="bi bi-info-circle"></i>
                  <span>Mid-Term results will be published within ten working days of the final paper. Rechecking
                    applications close two days after publication.</span>
                </div>
              </div>
            </div>
          </div>
        </section>
      <?php endif; ?>

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