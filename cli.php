<?php
// /app/cli.php
declare(strict_types=1);
require __DIR__ . '/repo.php';

function ask(string $prompt, ?string $default = null): string
{
    $suffix = $default !== null ? " [{$default}]" : "";
    echo $prompt . $suffix . ": ";
    $line = trim((string) fgets(STDIN));
    return $line === '' && $default !== null ? $default : $line;
}

function pause(): void { echo "\nPress Enter to continue..."; fgets(STDIN); }

function safe(callable $fn): void
{
    try {
        $r = $fn();
        if ($r !== null) { echo "\n"; print_r($r); }
        else { echo "\nOK.\n"; }
    } catch (AppException $e) {
        echo "\n⚠  " . $e->getMessage() . "\n";
    } catch (Throwable $e) {
        echo "\n✗ Unexpected error: " . $e->getMessage() . "\n";
    }
    pause();
}

function show_menu(): void
{
    echo "\n=== School DB CLI ===\n";
    echo "1.  List students\n";
    echo "2.  Add student\n";
    echo "3.  Update student\n";
    echo "4.  Delete student\n";
    echo "5.  List teachers\n";
    echo "6.  Add teacher\n";
    echo "7.  Delete teacher\n";
    echo "8.  Enroll student\n";
    echo "9.  Move student to another class arm\n";
    echo "10. Withdraw student from a session\n";
    echo "11. Show enrollments for a session\n";
    echo "12. Assign teacher to subject/class\n";
    echo "13. Unassign teacher\n";
    echo "14. Teacher's assignments\n";
    echo "15. Who teaches subject to class?\n";
    echo "16. Subjects available for a class\n";
    echo "0.  Exit\n";
}

while (true) {
    show_menu();
    $choice = ask("Choice");

    switch ($choice) {
        case '1':
            safe(fn() => student_list());
            break;

        case '2':
            safe(fn() => student_create(
                ask('Admission no'),
                ask('Full name'),
                ask('Gender (M/F, blank for none)', ''),
                ask('DOB YYYY-MM-DD (blank for none)', '')
            ));
            break;

        case '3':
            $id = (int) ask('Student id');
            $field = ask('Field (admission_no/full_name/gender/date_of_birth)');
            $value = ask('New value');
            safe(function() use ($id, $field, $value) {
                student_update($id, [$field => $value === '' ? null : $value]);
                return null;
            });
            break;

        case '4':
            safe(function() {
                student_delete((int) ask('Student id'));
                return null;
            });
            break;

        case '5':
            safe(fn() => teacher_list());
            break;

        case '6':
            safe(fn() => teacher_create(
                ask('Teacher name'),
                ask('Email (blank for none)', '')
            ));
            break;

        case '7':
            safe(function() {
                teacher_delete((int) ask('Teacher id'));
                return null;
            });
            break;

        case '8':
            safe(fn() => enroll(
                (int) ask('Student id'),
                (int) ask('Class arm id'),
                (int) ask('Session id')
            ));
            break;

        case '9':
            safe(function() {
                enrollment_move(
                    (int) ask('Student id'),
                    (int) ask('Session id'),
                    (int) ask('New class arm id')
                );
                return null;
            });
            break;

        case '10':
            safe(function() {
                enrollment_withdraw(
                    (int) ask('Student id'),
                    (int) ask('Session id')
                );
                return null;
            });
            break;

        case '11':
            safe(fn() => enrollments_for_session((int) ask('Session id')));
            break;

        case '12':
            safe(fn() => assign_teacher(
                (int) ask('Teacher id'),
                (int) ask('Subject id'),
                (int) ask('Class arm id'),
                (int) ask('Session id')
            ));
            break;

        case '13':
            safe(function() {
                unassign_teacher((int) ask('Assignment id'));
                return null;
            });
            break;

        case '14':
            safe(fn() => assignments_for_teacher(
                (int) ask('Teacher id'),
                ask('Session id (blank for any)', '') === '' ? null : (int) $lastAnswer
            ));
            break;

        case '15':
            safe(fn() => assignments_for_class(
                (int) ask('Subject id'),
                (int) ask('Class arm id'),
                (int) ask('Session id')
            ));
            break;

        case '16':
            safe(fn() => subjects_available_for_class((int) ask('Class id')));
            break;

        case '0':
            echo "Bye.\n";
            exit(0);

        default:
            echo "Unknown choice.\n";
    }
}