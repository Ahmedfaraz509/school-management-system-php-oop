<?php
// messages.php - Fully dynamic messages page with same design as exams

require_once '../database/connect.php';
require_once 'message_process.php';

// Get student ID from session (for demo, using student_id = 4)
$student_id = 4;

// Initialize the message process
$messageProcess = new MessageProcess($conn, $student_id);

// Get all data
$student = $messageProcess->getStudentData();
$inboxMessages = $messageProcess->getInboxMessages();
$sentMessages = $messageProcess->getSentMessages();
$draftMessages = $messageProcess->getDraftMessages();
$stats = $messageProcess->getMessageStats();
$contacts = $messageProcess->getContacts();
$unreadCount = $messageProcess->getUnreadCount();

// Format student name for display
$studentName = htmlspecialchars($student['first_name'] ?? 'Ahmed') . ' ' . htmlspecialchars($student['last_name'] ?? 'Faraz');
$studentInitials = strtoupper(substr($student['first_name'] ?? 'A', 0, 1) . substr($student['last_name'] ?? 'F', 0, 1));
$studentClass = htmlspecialchars($student['class_name'] ?? 'Class 10');
$studentUID = htmlspecialchars($student['student_uid'] ?? 'STU-1024');

// Get total inbox count
$inboxCount = count($inboxMessages);
$sentCount = count($sentMessages);
$draftCount = count($draftMessages);

// Get first message for display (if any)
$firstMessage = !empty($inboxMessages) ? $inboxMessages[0] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Messages — Crescent Public School Student Portal" />
  <title>Messages &middot; Student Portal &middot; Crescent Public School</title>
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

    .avatar.rose {
      background: linear-gradient(135deg, #f43f5e, #fb7185);
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

    /* ===== MESSAGE SPECIFIC STYLES ===== */
    .mbox-radio {
      display: none;
    }

    .mbox-tabs {
      display: flex;
      gap: 4px;
      padding: 4px;
      background: rgba(255, 255, 255, 0.03);
      border-radius: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .mbox-tabs label {
      padding: 8px 18px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 500;
      color: #7a8aa8;
      cursor: pointer;
      transition: all 0.2s;
      flex: 0 1 auto;
    }

    .mbox-tabs label:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
    }

    .mbox-radio:checked+.mbox-tabs label,
    .mbox-radio:checked~.mbox-tabs label {
      background: rgba(255, 255, 255, 0.06);
      color: #e8edf5;
    }

    .mbox-radio#mInbox:checked~.mbox-tabs label[for="mInbox"] {
      background: rgba(255, 255, 255, 0.06);
      color: #e8edf5;
    }

    .mbox-radio#mSent:checked~.mbox-tabs label[for="mSent"] {
      background: rgba(255, 255, 255, 0.06);
      color: #e8edf5;
    }

    .mbox-radio#mDraft:checked~.mbox-tabs label[for="mDraft"] {
      background: rgba(255, 255, 255, 0.06);
      color: #e8edf5;
    }

    .pane {
      display: none;
    }

    .mbox-radio#mInbox:checked~.mbox-panels .pane-inbox {
      display: block;
    }

    .mbox-radio#mSent:checked~.mbox-panels .pane-sent {
      display: block;
    }

    .mbox-radio#mDraft:checked~.mbox-panels .pane-draft {
      display: block;
    }

    .msg-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      cursor: pointer;
      transition: background 0.2s;
    }

    .msg-row:hover {
      background: rgba(255, 255, 255, 0.02);
      margin: 0 -8px;
      padding: 12px 8px;
      border-radius: 8px;
    }

    .msg-row.unread {
      background: rgba(96, 165, 250, 0.04);
      margin: 0 -8px;
      padding: 12px 8px;
      border-radius: 8px;
    }

    .msg-row .msg-body-col {
      flex: 1;
      min-width: 0;
    }

    .msg-row .msg-body-col h4 {
      font-size: 0.9rem;
      font-weight: 600;
      margin: 0 0 2px;
    }

    .msg-row .msg-body-col p {
      font-size: 0.8rem;
      opacity: 0.5;
      margin: 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .msg-row .msg-meta {
      font-size: 0.7rem;
      opacity: 0.4;
      text-align: right;
      flex-shrink: 0;
      min-width: 70px;
    }

    .msg-row .msg-meta .d-block {
      display: block;
    }

    .thread-head {
      padding: 20px 22px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .thread-body {
      padding: 20px 22px;
      font-size: 0.9rem;
      line-height: 1.7;
      opacity: 0.8;
    }

    .thread-body p {
      margin-bottom: 12px;
    }

    .thread-body p:last-child {
      margin-bottom: 0;
    }

    .alert-soft {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 18px;
      border-radius: 12px;
      background: rgba(52, 211, 153, 0.06);
      border-left: 3px solid #34d399;
      font-size: 0.85rem;
    }

    .alert-soft i {
      color: #34d399;
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

    .form-control::placeholder {
      color: #4a5a7a;
    }

    .form-select option {
      background: #141b2b;
      color: #e8edf5;
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

    .mt-2 {
      margin-top: 8px;
    }

    .mt-4 {
      margin-top: 24px;
    }

    .mb-0 {
      margin-bottom: 0;
    }

    .mb-1 {
      margin-bottom: 4px;
    }

    .mb-3 {
      margin-bottom: 16px;
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

    .align-items-start {
      align-items: flex-start;
    }

    .align-items-center {
      align-items: center;
    }

    .justify-content-between {
      justify-content: space-between;
    }

    .flex-1 {
      flex: 1;
    }

    .min-width-0 {
      min-width: 0;
    }

    .text-center {
      text-align: center;
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

      .msg-row .msg-meta {
        font-size: 0.6rem;
        min-width: 50px;
      }

      .thread-head {
        padding: 14px 16px;
      }

      .thread-body {
        padding: 14px 16px;
      }

      .mbox-tabs label {
        font-size: 0.75rem;
        padding: 6px 12px;
      }

      .col-xl-5 {
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

      .msg-row {
        padding: 10px 0;
      }

      .msg-row .msg-body-col h4 {
        font-size: 0.8rem;
      }

      .msg-row .msg-body-col p {
        font-size: 0.7rem;
      }

      .msg-row .msg-meta {
        font-size: 0.55rem;
        min-width: 40px;
      }

      .thread-body {
        font-size: 0.8rem;
      }

      .btn-solid,
      .btn-outline {
        font-size: 0.75rem;
        padding: 6px 14px;
      }

      .mbox-tabs {
        gap: 2px;
      }

      .mbox-tabs label {
        font-size: 0.65rem;
        padding: 4px 10px;
      }

      .avatar-lg {
        width: 36px;
        height: 36px;
        font-size: 0.8rem;
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

      .msg-row {
        border-bottom: 1px solid #eee !important;
      }

      .msg-row .msg-body-col h4 {
        color: #333 !important;
      }

      .msg-row .msg-body-col p {
        color: #555 !important;
        opacity: 1 !important;
      }

      .msg-row .msg-meta {
        color: #666 !important;
        opacity: 1 !important;
      }

      .thread-head {
        border-bottom: 2px solid #ddd !important;
      }

      .thread-head h3 {
        color: #333 !important;
      }

      .thread-body {
        color: #333 !important;
        opacity: 1 !important;
      }

      .avatar {
        border: 1px solid #ddd !important;
      }

      .form-control,
      .form-select {
        border: 1px solid #ddd !important;
        color: #333 !important;
        background: #f9f9f9 !important;
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
      <span class="avatar avatar-lg">
        <?php echo $studentInitials; ?>
      </span>
      <div class="student-card-text">
        <strong>
          <?php echo $studentName; ?>
        </strong>
        <small>
          <?php echo $studentClass . ' · ' . $studentUID; ?>
        </small>
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
      <a class="nav-item active" href="messages.php"><i class="bi bi-envelope"></i><span>Messages</span><em
          class="nav-tag nav-tag-danger">
          <?php echo $unreadCount; ?>
        </em></a>

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
        <h1>Messages</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>School Life<span>/</span>Messages</div>
      </div>
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="search" placeholder="Search conversations…" aria-label="Search" />
      </div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span class="ping">
              <?php echo min($unreadCount + 2, 9); ?>
            </span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <?php if (!empty($inboxMessages)): ?>
              <?php foreach (array_slice($inboxMessages, 0, 3) as $msg): ?>
                <a href="messages.php" class="drop-row">
                  <i class="dot-ico <?php echo $msg['sender_color']; ?>"><i class="bi bi-envelope"></i></i>
                  <span>
                    <p>
                      <?php echo htmlspecialchars(substr($msg['subject'] ?? 'New Message', 0, 30)) . (strlen($msg['subject'] ?? '') > 30 ? '...' : ''); ?>
                    </p>
                    <small>
                      <?php echo $msg['sender_name'] ?? 'Unknown'; ?> &middot;
                      <?php echo $msg['time_ago'] ?? 'Today'; ?>
                    </small>
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
          <a href="messages.php" class="icon-btn" aria-label="Messages">
            <i class="bi bi-envelope-open"></i>
            <span class="ping">
              <?php echo $unreadCount; ?>
            </span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Unread</strong><a href="messages.php">Open inbox</a></div>
            <?php
            $unreadMessages = array_filter($inboxMessages, function ($msg) {
              return $msg['is_read'] == 0;
            });
            ?>
            <?php if (!empty($unreadMessages)): ?>
              <?php foreach (array_slice($unreadMessages, 0, 3) as $msg): ?>
                <a href="messages.php" class="drop-row">
                  <span class="avatar <?php echo $msg['sender_color'] ?? 'info'; ?>">
                    <?php echo getInitials($msg['sender_name'] ?? 'U'); ?>
                  </span>
                  <span>
                    <p>
                      <?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?> &middot;
                      <?php echo htmlspecialchars(substr($msg['subject'] ?? 'New Message', 0, 20)); ?>
                    </p>
                    <small>
                      <?php echo $msg['time_ago'] ?? 'Today'; ?>
                    </small>
                  </span>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="drop-row"><span>
                  <p style="opacity:0.4;">No unread messages</p>
                </span></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="icon-drop">
          <a href="profile.php" class="profile-chip">
            <span class="avatar">
              <?php echo $studentInitials; ?>
            </span>
            <span class="who"><b>
                <?php echo htmlspecialchars($student['first_name'] ?? 'Ahmed'); ?>
              </b><small>
                <?php echo $studentClass; ?>
              </small></span>
            <i class="bi bi-chevron-down"></i>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>
                <?php echo $studentName; ?>
              </strong><span class="pill p-ok">Active</span></div>
            <a href="profile.php" class="drop-row"><i class="dot-ico p-teal"><i
                  class="bi bi-person-badge"></i></i><span>
                <p>My Profile</p><small>
                  <?php echo $studentUID; ?>
                </small>
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
        <b>Debug:</b> Found <b>
          <?php echo $inboxCount; ?>
        </b> inbox messages, <b>
          <?php echo $sentCount; ?>
        </b> sent messages.
        <?php if ($inboxCount == 0 && $sentCount == 0): ?>
          <span style="color:#f87171;">No messages found! Please add sample data to the messages table.</span>
        <?php else: ?>
          <span style="color:#34d399;">Messages loaded successfully!</span>
        <?php endif; ?>
      </div>

      <!-- ===== WELCOME SECTION ===== -->
      <section class="welcome rise">
        <div class="row g-4 align-items-center">
          <div class="col-lg-8">
            <span class="eyebrow">Inbox &middot; Secure school messaging</span>
            <h2>
              <?php echo $unreadCount; ?> unread
              <?php echo $unreadCount == 1 ? 'message' : 'messages'; ?> waiting
            </h2>
            <p>Communicate directly with subject teachers, your class teacher and the school office. Switch between
              Inbox, Sent and Drafts using the tabs below &mdash; no page reload required.</p>
            <div class="quick-chips">
              <a href="attendance.php" class="chip"><i class="bi bi-envelope-plus"></i> Apply for Leave</a>
              <a href="assignments.php" class="chip"><i class="bi bi-chat-dots"></i> Ask about Homework</a>
              <a href="fees.php" class="chip"><i class="bi bi-wallet2"></i> Accounts Query</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact">
                  <small>Inbox</small>
                  <strong>
                    <?php echo str_pad($inboxCount, 2, '0', STR_PAD_LEFT); ?>
                  </strong>
                  <span>
                    <?php echo $unreadCount; ?> unread
                  </span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Sent</small>
                  <strong>
                    <?php echo str_pad($sentCount, 2, '0', STR_PAD_LEFT); ?>
                  </strong>
                  <span>This term</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Drafts</small>
                  <strong>
                    <?php echo str_pad($draftCount, 2, '0', STR_PAD_LEFT); ?>
                  </strong>
                  <span>Unsent</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Avg. Reply</small>
                  <strong>&lt; 1 day</strong>
                  <span>From staff</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== MESSAGES ===== -->
      <section class="mt-4 rise">

        <div class="mbox">
          <input type="radio" name="mbox" id="mInbox" class="mbox-radio" checked />
          <input type="radio" name="mbox" id="mSent" class="mbox-radio" />
          <input type="radio" name="mbox" id="mDraft" class="mbox-radio" />

          <div class="mbox-tabs">
            <label for="mInbox"><i class="bi bi-inbox me-1"></i> Inbox <span class="pill p-danger ms-1 bare">
                <?php echo $unreadCount; ?>
              </span></label>
            <label for="mSent"><i class="bi bi-send me-1"></i> Sent</label>
            <label for="mDraft"><i class="bi bi-file-earmark me-1"></i> Drafts <span class="pill p-warn ms-1 bare">
                <?php echo $draftCount; ?>
              </span></label>
          </div>

          <div class="mbox-panels">
            <div class="row g-3">

              <!-- INBOX LIST -->
              <div class="col-xl-5">
                <div class="pane pane-inbox">
                  <?php if (!empty($inboxMessages)): ?>
                    <?php foreach ($inboxMessages as $msg): ?>
                      <div class="msg-row <?php echo $msg['is_read'] == 0 ? 'unread' : ''; ?>">
                        <span class="avatar <?php echo $msg['sender_color'] ?? 'info'; ?>">
                          <?php echo getInitials($msg['sender_name'] ?? 'U'); ?>
                        </span>
                        <div class="msg-body-col">
                          <h4>
                            <?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?>
                          </h4>
                          <p><b>
                              <?php echo htmlspecialchars($msg['subject'] ?? 'No Subject'); ?>
                            </b> &mdash;
                            <?php echo formatPreview($msg['content'] ?? '', 60); ?>
                          </p>
                        </div>
                        <div class="msg-meta">
                          <?php echo $msg['time_ago'] ?? 'Today'; ?>
                          <span class="d-block mt-1"><span class="pill <?php echo $msg['read_color']; ?>">
                              <?php echo $msg['read_status']; ?>
                            </span></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div style="text-align:center;padding:40px 20px;opacity:0.4;">
                      <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:12px;"></i>
                      <p>No messages in your inbox</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- READING PANE -->
              <div class="col-xl-7">
                <div class="pane pane-inbox">
                  <div class="card lift h-100">
                    <?php if ($firstMessage): ?>
                      <div class="thread-head">
                        <div class="d-flex align-items-start gap-3">
                          <span class="avatar avatar-lg <?php echo $firstMessage['sender_color'] ?? 'info'; ?>">
                            <?php echo getInitials($firstMessage['sender_name'] ?? 'U'); ?>
                          </span>
                          <div style="flex:1;min-width:0">
                            <h3 style="font-size:1.05rem">
                              <?php echo htmlspecialchars($firstMessage['subject'] ?? 'No Subject'); ?>
                            </h3>
                            <p class="mb-0" style="font-size:.78rem;color:#5a6a8a;">
                              <b style="color:#e8edf5;">
                                <?php echo htmlspecialchars($firstMessage['sender_name'] ?? 'Unknown'); ?>
                              </b><br />
                              To:
                              <?php echo $studentName; ?> &middot;
                              <?php echo $firstMessage['formatted_date'] ?? date('M d, Y'); ?>,
                              <?php echo $firstMessage['formatted_time'] ?? date('h:i A'); ?>
                            </p>
                          </div>
                          <span class="pill <?php echo $firstMessage['sender_color'] ?? 'p-info'; ?>">
                            <?php echo getSenderTypeLabel($firstMessage['sender_type'] ?? 'Student'); ?>
                          </span>
                        </div>
                      </div>
                      <div class="thread-body">
                        <?php
                        $content = $firstMessage['content'] ?? 'No content available.';
                        $paragraphs = explode("\n", $content);
                        foreach ($paragraphs as $p):
                          if (trim($p) == '')
                            continue;
                          ?>
                          <p>
                            <?php echo nl2br(htmlspecialchars(trim($p))); ?>
                          </p>
                        <?php endforeach; ?>
                      </div>
                      <div class="card-body" style="border-top:1px solid rgba(255,255,255,0.04);">
                        <div class="d-flex flex-wrap gap-2">
                          <a href="#" class="btn-solid"><i class="bi bi-reply"></i> Reply</a>
                          <a href="#" class="btn-outline"><i class="bi bi-reply-all"></i> Reply All</a>
                          <a href="#" class="btn-outline"><i class="bi bi-arrow-right"></i> Forward</a>
                          <a href="#" class="btn-outline"><i class="bi bi-star"></i> Star</a>
                          <a href="#" class="btn-outline"><i class="bi bi-archive"></i> Archive</a>
                        </div>
                      </div>
                    <?php else: ?>
                      <div style="text-align:center;padding:60px 20px;opacity:0.4;">
                        <i class="bi bi-envelope" style="font-size:2.5rem;display:block;margin-bottom:12px;"></i>
                        <p>Select a message to read</p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- SENT -->
              <div class="col-12">
                <div class="pane pane-sent">
                  <div class="card lift">
                    <div class="card-head">
                      <h3>Sent Messages <span class="sub">
                          <?php echo $sentCount; ?> messages this term
                        </span></h3>
                    </div>
                    <div class="card-body">
                      <?php if (!empty($sentMessages)): ?>
                        <?php foreach ($sentMessages as $msg): ?>
                          <div class="msg-row">
                            <span class="avatar <?php echo $msg['sender_color'] ?? 'info'; ?>">
                              <?php echo getInitials($msg['recipient_name'] ?? 'R'); ?>
                            </span>
                            <div class="msg-body-col">
                              <h4>To:
                                <?php echo htmlspecialchars($msg['recipient_name'] ?? 'Unknown'); ?>
                              </h4>
                              <p><b>
                                  <?php echo htmlspecialchars($msg['subject'] ?? 'No Subject'); ?>
                                </b> &mdash;
                                <?php echo formatPreview($msg['content'] ?? '', 60); ?>
                              </p>
                            </div>
                            <div class="msg-meta">
                              <?php echo $msg['time_ago'] ?? 'Today'; ?>
                              <span class="d-block mt-1"><span class="pill p-ok">Delivered</span></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div style="text-align:center;padding:30px 20px;opacity:0.4;">
                          <i class="bi bi-send" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                          <p>No sent messages</p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- DRAFTS -->
              <div class="col-12">
                <div class="pane pane-draft">
                  <div class="card lift">
                    <div class="card-head">
                      <h3>Drafts <span class="sub">
                          <?php echo $draftCount; ?> unsent messages
                        </span></h3>
                    </div>
                    <div class="card-body">
                      <?php if (!empty($draftMessages)): ?>
                        <?php foreach ($draftMessages as $msg): ?>
                          <div class="msg-row">
                            <span class="avatar <?php echo $msg['sender_color'] ?? 'info'; ?>">
                              <?php echo getInitials($msg['sender_name'] ?? 'D'); ?>
                            </span>
                            <div class="msg-body-col">
                              <h4>To:
                                <?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?>
                              </h4>
                              <p><b>
                                  <?php echo htmlspecialchars($msg['subject'] ?? 'No Subject'); ?>
                                </b> &mdash; Draft &mdash;
                                <?php echo formatPreview($msg['content'] ?? '', 50); ?>
                              </p>
                            </div>
                            <div class="msg-meta">
                              <?php echo $msg['time_ago'] ?? 'Today'; ?>
                              <span class="d-block mt-1"><span class="pill p-warn">Draft</span></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div style="text-align:center;padding:30px 20px;opacity:0.4;">
                          <i class="bi bi-file-earmark" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                          <p>No draft messages</p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </section>

      <!-- ===== COMPOSE + CONTACTS ===== -->
      <section class="row g-3 mt-1 rise">
        <div class="col-xl-7">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Compose Message <span class="sub">Front-end demo &mdash; nothing is transmitted</span></h3>
            </div>
            <div class="card-body">
              <form>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="to">Recipient</label>
                    <select class="form-select" id="to">
                      <option selected>Ms. Sara Khan — Physics</option>
                      <option>Mr. Ahmed Raza — Mathematics</option>
                      <option>Mr. Bilal Ahmad — Chemistry</option>
                      <option>Mr. Ali Hassan — Computer Science</option>
                      <option>Ms. Hina Malik — English</option>
                      <option>Mr. Tariq Mehmood — Urdu</option>
                      <option>Ms. Farah Noor — Class Teacher</option>
                      <option>Admin Office</option>
                      <option>Accounts Office</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="subject">Subject</label>
                    <input type="text" class="form-control" id="subject" value="Query regarding Mid-Term syllabus" />
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="body">Message</label>
                    <textarea class="form-control" id="body" rows="6">Respected Ma'am,

Could you please confirm which chapters from the revised syllabus will be included in the Mid-Term Physics paper? I would also like to know whether numericals from Chapter 13 are part of the assessment.

Thank you.
Ahmed Faraz — Class 10-A, STU-1024</textarea>
                  </div>
                  <div class="col-12 d-flex flex-wrap gap-2">
                    <a href="#" class="btn-solid"><i class="bi bi-send"></i> Send Message</a>
                    <a href="#" class="btn-outline"><i class="bi bi-paperclip"></i> Attach File</a>
                    <a href="#" class="btn-outline"><i class="bi bi-save"></i> Save Draft</a>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Frequent Contacts <span class="sub">Teachers &amp; departments</span></h3>
            </div>
            <div class="card-body">
              <?php if (!empty($contacts)): ?>
                <?php foreach ($contacts as $contact): ?>
                  <div class="msg-row">
                    <span class="avatar <?php echo $contact['avatar_color'] ?? 'info'; ?>">
                      <?php echo getInitials($contact['full_name'] ?? 'C'); ?>
                    </span>
                    <div class="msg-body-col">
                      <h4>
                        <?php echo htmlspecialchars($contact['full_name'] ?? 'Unknown'); ?>
                      </h4>
                      <p>
                        <?php echo htmlspecialchars($contact['type'] ?? 'Staff'); ?>
                        <?php echo !empty($contact['email']) ? '· ' . htmlspecialchars($contact['email']) : ''; ?>
                      </p>
                    </div>
                    <div class="msg-meta"><span class="pill p-teal">Online</span></div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="text-align:center;padding:20px;opacity:0.4;">
                  <i class="bi bi-people" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                  <p>No contacts available</p>
                </div>
              <?php endif; ?>
              <div class="divider-soft"></div>
              <div class="alert-soft teal">
                <i class="bi bi-shield-lock"></i>
                <span>All messages are logged for safeguarding purposes. Responses from staff usually arrive within one
                  working day.</span>
              </div>
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