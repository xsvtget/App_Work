<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$month = isset($_GET["month"]) ? (int)$_GET["month"] : (int)date("n");
$year = isset($_GET["year"]) ? (int)$_GET["year"] : (int)date("Y");

if ($month < 1 || $month > 12) {
    $month = (int)date("n");
}

if ($year < 2000 || $year > 2100) {
    $year = (int)date("Y");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Template</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
            padding: 20px;
        }

        .page {
            max-width: 900px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            text-decoration: none;
            color: #14a39d;
            font-weight: 700;
        }

        .card {
            background: #ffffff;
            border-radius: 22px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 18px;
        }

        h1 {
            margin-top: 0;
            font-size: 30px;
            color: #0f274f;
        }

        h2 {
            color: #0f274f;
            margin-top: 0;
        }

        p {
            line-height: 1.6;
            color: #475569;
        }

        .important {
            background: #ecfeff;
            border-left: 5px solid #14b8a6;
            padding: 14px 16px;
            border-radius: 14px;
            margin-top: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            overflow: hidden;
            border-radius: 16px;
        }

        th, td {
            padding: 14px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #0f274f;
            color: #ffffff;
        }

        .sample {
            background: #f8fafc;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 14px;
            padding: 12px 18px;
            background: #14b8a6;
            color: #ffffff;
            font-weight: 700;
            margin-top: 16px;
        }

        ul {
            line-height: 1.8;
            color: #475569;
            padding-left: 20px;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .card {
                padding: 18px;
            }

            h1 {
                font-size: 24px;
            }

            th, td {
                padding: 10px;
                font-size: 14px;
            }
        }
        .template-btn,
        .download-btn {
            display: inline-block;
            background: #007bff;
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .template-btn:hover,
        .download-btn:hover {
            background: #0056b3;
            color: #fff;
            text-decoration: none;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="page">
        <a href="dashboard.php" class="back-link">← Back to dashboard</a>

        <div class="card">
            <h1>Excel import template</h1>
            <p>
                Your Excel file must contain exactly 3 columns:
                <strong>Date</strong>, <strong>Start</strong>, and <strong>End</strong>.
            </p>

            <div class="important">
                Use this format:
                <br><strong>Date</strong> = YYYY-MM-DD
                <br><strong>Start / End</strong> = decimal numbers
            </div>
        </div>

        <div class="card">
            <h2>Correct example</h2>

            <table>
                <tr>
                    <th>Date</th>
                    <th>Start</th>
                    <th>End</th>
                </tr>
                <tr class="sample">
                    <td>2026-03-01</td>
                    <td>13</td>
                    <td>21</td>
                </tr>
                <tr class="sample">
                    <td>2026-03-04</td>
                    <td>16</td>
                    <td>22</td>
                </tr>
                <tr class="sample">
                    <td>2026-03-13</td>
                    <td>16.5</td>
                    <td>21.5</td>
                </tr>
                <tr class="sample">
                    <td>2026-03-20</td>
                    <td>17</td>
                    <td>22</td>
                </tr>
            </table>

            <a href="download_template.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="download-btn">
                Download template
            </a>
        </div>

        <div class="card">
            <h2>Important rules</h2>
            <ul>
                <li>The first row must be headers: <strong>Date</strong>, <strong>Start</strong>, <strong>End</strong>.</li>
                <li>Date should be written like: <strong>2026-03-01</strong>.</li>
                <li>Time should be decimal: <strong>13</strong>, <strong>16.5</strong>, <strong>21.5</strong>.</li>
                <li><strong>16.5</strong> means 16:30.</li>
                <li>Do not add extra title rows like month names or usernames above the table.</li>
                <li>Do not merge cells.</li>
                <li>Colors in Excel are fine, but the data must still stay in the same 3 columns.</li>
            </ul>
        </div>
    </div>
</body>
</html>