<?php
// settings.php - Settings page with same design as exams

require_once '../database/connect.php';

// Get student ID from session (for demo, using student_id = 4)
$student_id = 4;

// Get student data
$studentQuery = "SELECT s.*, c.name as class_name, c.grade 
                 FROM students s 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 WHERE s.id = ?";
$stmt = $conn->prepare($studentQuery);
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Format student name for display
$studentName = htmlspecialchars($student['first_name'] ?? 'Ahmed') . ' ' . htmlspecialchars($student['last_name'] ?? 'Faraz');
$studentInitials = strtoupper(substr($student['first_name'] ?? 'A', 0, 1) . substr($student['last_name'] ?? 'F', 0, 1));
$studentClass = htmlspecialchars($student['class_name'] ?? 'Class 10');
$studentUID = htmlspecialchars($student['student_uid'] ?? 'STU-1024');
$studentEmail = htmlspecialchars($student['email'] ?? 'student@crescent.edu');
$studentPhone = htmlspecialchars($student['phone'] ?? '+92 300 1234567');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Settings — Crescent Public School Student Portal" />
  <title>Settings &middot; Student Portal &middot; Crescent Public School</title>
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

    .avatar.info {
      background: linear-gradient(135deg, #3b82f6, #6366f1);
    }

    .avatar.ok {
      background: linear-gradient(135deg, #34d399, #6ee7b7);
    }

    .avatar.amber {
      background: linear-gradient(135deg, #f59e0b, #fbbf24);
    }

    .avatar.violet {
      background: linear-gradient(135deg, #8b5cf6, #a78bfa);
    }

    .avatar.danger {
      background: linear-gradient(135deg, #ef4444, #f87171);
    }

    .avatar.teal {
      background: linear-gradient(135deg, #14b8a6, #5eead4);
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

    .divider-soft {
      height: 1px;
      background: rgba(255, 255, 255, 0.04);
      margin: 12px 0;
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

    .alert-soft.danger {
      background: rgba(239, 68, 68, 0.06);
      border-left-color: #f87171;
    }

    .alert-soft.danger i {
      color: #f87171;
    }

    .form-label {
      font-size: 0.8rem;
      font-weight: 500;
      opacity: 0.6;
      margin-bottom: 4px;
    }

    .form-control,
    .form-select {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.06);
      color: #e8edf5;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 0.85rem;
      transition: all 0.2s;
    }

    .form-control:focus,
    .form-select:focus {
      background: rgba(255, 255, 255, 0.06);
      border-color: rgba(96, 165, 250, 0.3);
      box-shadow: none;
      color: #e8edf5;
    }

    .form-control[readonly] {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .form-control::placeholder {
      color: #4a5a7a;
    }

    .form-select option {
      background: #141b2b;
      color: #e8edf5;
    }

    .form-text {
      font-size: 0.75rem;
      opacity: 0.4;
      margin-top: 4px;
    }

    .check-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      cursor: pointer;
    }

    .check-line:last-child {
      border-bottom: none;
    }

    .check-line .txt b {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .check-line .txt small {
      display: block;
      font-size: 0.7rem;
      opacity: 0.4;
      font-weight: 400;
    }

    .check-line input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: #6366f1;
      cursor: pointer;
      flex-shrink: 0;
    }

    .check-line.box {
      padding: 12px 14px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.04);
      margin-bottom: 8px;
    }

    .check-line.box:last-child {
      margin-bottom: 0;
    }

    .switch {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 24px;
      flex-shrink: 0;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .switch .track {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 24px;
      transition: all 0.3s;
    }

    .switch .track::before {
      content: '';
      position: absolute;
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background: #5a6a8a;
      border-radius: 50%;
      transition: all 0.3s;
    }

    .switch input:checked+.track {
      background: linear-gradient(135deg, #3b82f6, #6366f1);
    }

    .switch input:checked+.track::before {
      transform: translateX(20px);
      background: white;
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

    .gap-2 {
      gap: 8px;
    }

    .gap-3 {
      gap: 16px;
    }

    .gap-4 {
      gap: 24px;
    }

    .mt-1 {
      margin-top: 4px;
    }

    .mt-2 {
      margin-top: 8px;
    }

    .mt-4 {
      margin-top: 24px;
    }

    .mb-0 {
      margin-bottom: 0;
    }

    .me-1 {
      margin-right: 4px;
    }

    .ms-1 {
      margin-left: 4px;
    }

    .d-block {
      display: block;
    }

    .d-flex {
      display: flex;
    }

    .flex-wrap {
      flex-wrap: wrap;
    }

    .align-items-center {
      align-items: center;
    }

    .justify-content-between {
      justify-content: space-between;
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

      .col-xl-6 {
        margin-bottom: 16px;
      }

      .col-xl-4 {
        margin-bottom: 16px;
      }

      .check-line .txt small {
        font-size: 0.65rem;
      }

      .switch {
        width: 38px;
        height: 20px;
      }

      .switch .track::before {
        height: 14px;
        width: 14px;
        left: 3px;
        bottom: 3px;
      }

      .switch input:checked+.track::before {
        transform: translateX(18px);
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

      .card .card-head h3 {
        font-size: 0.9rem;
      }

      .card .card-head h3 .sub {
        font-size: 0.6rem;
      }

      .pill {
        font-size: 0.6rem;
        padding: 1px 8px;
      }

      .form-control,
      .form-select {
        font-size: 0.8rem;
        padding: 8px 12px;
      }

      .check-line {
        padding: 8px 0;
      }

      .check-line .txt b {
        font-size: 0.8rem;
      }

      .check-line .txt small {
        font-size: 0.6rem;
      }

      .btn-solid,
      .btn-outline {
        font-size: 0.75rem;
        padding: 6px 14px;
      }

      .meta-list li {
        font-size: 0.75rem;
      }

      .alert-soft {
        font-size: 0.75rem;
        padding: 10px 14px;
      }
    }

    @media print {

      .app-sidebar,
      .app-topbar,
      .page-foot {
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

      .form-control,
      .form-select {
        border: 1px solid #ddd !important;
        color: #333 !important;
        background: #f9f9f9 !important;
      }

      .check-line {
        border-bottom: 1px solid #eee !important;
      }

      .check-line .txt b {
        color: #333 !important;
      }

      .check-line .txt small {
        color: #666 !important;
        opacity: 1 !important;
      }

      .alert-soft {
        border-left-color: #666 !important;
        background: #f5f5f5 !important;
        color: #333 !important;
      }

      .alert-soft i {
        color: #666 !important;
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
      <a class="nav-item" href="notices.php"><i class="bi bi-megaphone"></i><span>Notices</span></a>
      <a class="nav-item" href="events.php"><i class="bi bi-calendar2-heart"></i><span>Events</span></a>
      <a class="nav-item" href="messages.php"><i class="bi bi-envelope"></i><span>Messages</span></a>

      <p class="nav-group">Account</p>
      <a class="nav-item" href="profile.php"><i class="bi bi-person-badge"></i><span>My Profile</span></a>
      <a class="nav-item active" href="settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
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
        <h1>Settings</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>Account<span>/</span>Settings</div>
      </div>
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="search" placeholder="Search settings…" aria-label="Search" />
      </div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span class="ping">5</span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <a href="exams.php" class="drop-row">
              <i class="dot-ico p-info"><i class="bi bi-pencil-square"></i></i>
              <span>
                <p>Mid-Term timetable published</p><small>Examination Cell · 2 hours ago</small>
              </span>
            </a>
            <a href="fees.php" class="drop-row">
              <i class="dot-ico p-danger"><i class="bi bi-wallet2"></i></i>
              <span>
                <p>Fee instalment due on Sep 10</p><small>Accounts Office · 3 days ago</small>
              </span>
            </a>
          </div>
        </div>
        <div class="icon-drop">
          <a href="messages.php" class="icon-btn" aria-label="Messages">
            <i class="bi bi-envelope"></i>
            <span class="ping">3</span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Messages</strong><a href="messages.php">Open inbox</a></div>
            <a href="messages.php" class="drop-row">
              <span class="avatar info">SR</span>
              <span>
                <p>Ms. Sara Khan · Lab report feedback</p><small>Today, 09:14 AM</small>
              </span>
            </a>
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

      <!-- ===== WELCOME SECTION ===== -->
      <section class="welcome rise">
        <div class="row g-4 align-items-center">
          <div class="col-lg-8">
            <span class="eyebrow">Preferences &middot; <?php echo $studentName; ?> (<?php echo $studentUID; ?>)</span>
            <h2>Manage your portal preferences</h2>
            <p>Update your account details, choose how dates and times are displayed, and control which notifications
              reach you. Every control on this page is styled with pure CSS &mdash; no JavaScript is used.</p>
            <div class="quick-chips">
              <a href="profile.php" class="chip"><i class="bi bi-person-badge"></i> My Profile</a>
              <a href="messages.php" class="chip"><i class="bi bi-envelope"></i> Messages</a>
              <a href="notices.php" class="chip"><i class="bi bi-megaphone"></i> Notices</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact">
                  <small>Alerts On</small>
                  <strong>05</strong>
                  <span>Channels</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Language</small>
                  <strong>English</strong>
                  <span>Interface</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Timezone</small>
                  <strong>PKT</strong>
                  <span>UTC +05:00</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Last Saved</small>
                  <strong><?php echo date('d M'); ?></strong>
                  <span><?php echo date('Y'); ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== ACCOUNT SETTINGS + PREFERENCES ===== -->
      <section class="row g-3 mt-1">

        <!-- ACCOUNT SETTINGS -->
        <div class="col-xl-6 rise">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Account Settings <span class="sub">Basic contact information</span></h3>
            </div>
            <div class="card-body">
              <form>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label" for="accName">Name</label>
                    <input type="text" class="form-control" id="accName" value="<?php echo $studentName; ?>" />
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="accEmail">Email</label>
                    <input type="email" class="form-control" id="accEmail" value="<?php echo $studentEmail; ?>" />
                    <div class="form-text">A confirmation link is sent whenever the email address changes.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="accPhone">Phone</label>
                    <input type="tel" class="form-control" id="accPhone" value="<?php echo $studentPhone; ?>" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="accStudent">Student ID</label>
                    <input type="text" class="form-control" id="accStudent" value="<?php echo $studentUID; ?>"
                      readonly />
                  </div>
                  <div class="col-12">
                    <div class="divider-soft"></div>
                    <label class="form-label">Change Password</label>
                    <div class="row g-3">
                      <div class="col-md-6"><input type="password" class="form-control" placeholder="Current password"
                          aria-label="Current password" /></div>
                      <div class="col-md-6"><input type="password" class="form-control" placeholder="New password"
                          aria-label="New password" /></div>
                    </div>
                    <div class="form-text mt-2"><i class="bi bi-shield-lock me-1"></i>Use at least 8 characters with one
                      number and one symbol.</div>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- PREFERENCES -->
        <div class="col-xl-6 rise rise-2">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Preferences <span class="sub">Regional display options</span></h3>
            </div>
            <div class="card-body">
              <form>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label" for="lang">Language</label>
                    <select class="form-select" id="lang">
                      <option selected>English (United Kingdom)</option>
                      <option>Urdu (Pakistan)</option>
                      <option>Arabic</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="tz">Timezone</label>
                    <select class="form-select" id="tz">
                      <option selected>(UTC +05:00) Pakistan Standard Time — Karachi</option>
                      <option>(UTC +04:00) Gulf Standard Time — Dubai</option>
                      <option>(UTC +00:00) Greenwich Mean Time — London</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="dfmt">Date Format</label>
                    <select class="form-select" id="dfmt">
                      <option selected>Aug 24, 2026 (MMM DD, YYYY)</option>
                      <option>24/08/2026 (DD/MM/YYYY)</option>
                      <option>08/24/2026 (MM/DD/YYYY)</option>
                      <option>2026-08-24 (ISO 8601)</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label d-block">Week Starts On</label>
                    <div class="d-flex flex-wrap gap-4">
                      <label class="d-flex align-items-center gap-2" style="font-size:.85rem"><input type="radio"
                          name="weekstart" checked /> Monday</label>
                      <label class="d-flex align-items-center gap-2" style="font-size:.85rem"><input type="radio"
                          name="weekstart" /> Sunday</label>
                      <label class="d-flex align-items-center gap-2" style="font-size:.85rem"><input type="radio"
                          name="weekstart" /> Saturday</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label d-block">Time Display</label>
                    <div class="d-flex flex-wrap gap-4">
                      <label class="d-flex align-items-center gap-2" style="font-size:.85rem"><input type="radio"
                          name="tformat" checked /> 12-hour (09:00 AM)</label>
                      <label class="d-flex align-items-center gap-2" style="font-size:.85rem"><input type="radio"
                          name="tformat" /> 24-hour (09:00)</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="check-line box">
                      <span class="txt"><b>Compact table view</b><small>Reduce row height across all
                          tables</small></span>
                      <input type="checkbox" />
                    </label>
                    <label class="check-line box">
                      <span class="txt"><b>Show percentage beside grades</b><small>Display % next to every grade in
                          results</small></span>
                      <input type="checkbox" checked />
                    </label>
                    <label class="check-line box">
                      <span class="txt"><b>Reduce animations</b><small>Turn off card hover motion across the
                          portal</small></span>
                      <input type="checkbox" />
                    </label>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== NOTIFICATIONS ===== -->
      <section class="mt-4 rise">
        <div class="card lift">
          <div class="card-head">
            <h3>Notification Settings <span class="sub">Choose what you want to be alerted about</span></h3>
            <span class="pill p-ok"><i class="bi bi-bell"></i> 5 of 6 enabled</span>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-lg-6">
                <label class="check-line">
                  <span class="txt"><b>Email Notifications</b><small>Receive a daily summary digest in your
                      inbox</small></span>
                  <span class="switch"><input type="checkbox" checked /><span class="track"></span></span>
                </label>
                <label class="check-line">
                  <span class="txt"><b>Assignment Notifications</b><small>New homework and upcoming deadline
                      reminders</small></span>
                  <span class="switch"><input type="checkbox" checked /><span class="track"></span></span>
                </label>
                <label class="check-line">
                  <span class="txt"><b>Exam Notifications</b><small>Date sheets, admit cards and result
                      announcements</small></span>
                  <span class="switch"><input type="checkbox" checked /><span class="track"></span></span>
                </label>
              </div>
              <div class="col-lg-6">
                <label class="check-line">
                  <span class="txt"><b>Attendance Notifications</b><small>Absence and late arrival
                      records</small></span>
                  <span class="switch"><input type="checkbox" checked /><span class="track"></span></span>
                </label>
                <label class="check-line">
                  <span class="txt"><b>Notice Notifications</b><small>School circulars and important
                      announcements</small></span>
                  <span class="switch"><input type="checkbox" checked /><span class="track"></span></span>
                </label>
                <label class="check-line">
                  <span class="txt"><b>Fee &amp; Payment Alerts</b><small>Invoice generation and due date
                      reminders</small></span>
                  <span class="switch"><input type="checkbox" /><span class="track"></span></span>
                </label>
              </div>

              <div class="col-12">
                <div class="divider-soft"></div>
                <label class="form-label d-block">Quiet Hours</label>
                <div class="row g-3">
                  <div class="col-md-4"><input type="time" class="form-control" value="21:00"
                      aria-label="Quiet hours start" /></div>
                  <div class="col-md-4"><input type="time" class="form-control" value="06:30"
                      aria-label="Quiet hours end" /></div>
                  <div class="col-md-4 d-flex align-items-center"><span class="form-text">No alerts are delivered
                      between these times.</span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body" style="border-top:1px solid rgba(255,255,255,0.04);">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
              <span style="font-size:.76rem;color:#5a6a8a;"><i class="bi bi-info-circle me-1"></i>This is a static demo
                page. Preference changes are not transmitted or stored.</span>
              <span class="d-flex gap-2">
                <a href="settings.php" class="btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Restore
                  Defaults</a>
                <a href="settings.php" class="btn-solid"><i class="bi bi-check2"></i> Save Preferences</a>
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== PRIVACY + DEVICES + HELP ===== -->
      <section class="row g-3 mt-1 rise">
        <div class="col-xl-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Privacy <span class="sub">Data visibility</span></h3>
            </div>
            <div class="card-body">
              <label class="check-line box"><span class="txt"><b>Show result to classmates</b><small>Allow peers to view
                    my grades on shared boards</small></span><input type="checkbox" /></label>
              <label class="check-line box"><span class="txt"><b>Appear in student directory</b><small>List my name in
                    the <?php echo $studentClass; ?> directory</small></span><input type="checkbox" checked /></label>
              <label class="check-line box"><span class="txt"><b>Event photography consent</b><small>Allow photographs
                    in school publications</small></span><input type="checkbox" checked /></label>
            </div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Linked Devices <span class="sub">Active sessions</span></h3>
            </div>
            <div class="card-body">
              <ul class="meta-list">
                <li><i class="bi bi-laptop"></i> Chrome · Windows <span>Current</span></li>
                <li><i class="bi bi-phone"></i> Mobile · Android <span>Active</span></li>
                <li><i class="bi bi-tablet"></i> Tablet · iPad <span>Inactive</span></li>
              </ul>
              <div class="divider-soft"></div>
              <a href="settings.php" class="btn-outline w-100 justify-content-center"><i
                  class="bi bi-box-arrow-up-right"></i> Sign out other devices</a>
            </div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Help &amp; Support <span class="sub">Where to get assistance</span></h3>
            </div>
            <div class="card-body">
              <ul class="meta-list">
                <li><i class="bi bi-question-circle"></i> Help Centre <span>Portal Guide</span></li>
                <li><i class="bi bi-headset"></i> IT Desk <span>Ext. 100</span></li>
                <li><i class="bi bi-envelope"></i> Support Email <span>support@crescent.edu</span></li>
                <li><i class="bi bi-clock"></i> Support Hours <span>08:00 – 03:00</span></li>
              </ul>
              <div class="divider-soft"></div>
              <a href="messages.php" class="btn-solid w-100 justify-content-center"><i class="bi bi-chat-dots"></i>
                Contact Support</a>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== DANGER ZONE ===== -->
      <section class="mt-4 rise">
        <div class="alert-soft danger">
          <i class="bi bi-exclamation-triangle"></i>
          <span><b>Need to change your name, date of birth or admission record?</b> These fields are locked in the
            student portal. Submit a written request with supporting documents to the school office for
            verification.</span>
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