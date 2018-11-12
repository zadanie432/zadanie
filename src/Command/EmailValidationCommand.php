<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Validation;
use \Symfony\Component\Validator\Validator\ValidatorInterface;

class EmailValidationCommand extends Command
{
    /**
     * @property  ValidatorInterface validator
     */
    private $validator;
    private $email;
    private $valid = 0;
    private $inValid = 0;
    private $fileNameValid = 'poprawne.txt';
    private $fileNameInValid = 'niePoprawne.txt';
    private $fileNameSumary = 'podsumowanie.txt';

    protected function configure(): void
    {
        $this
            ->setName('app:email-validation')
            ->setDescription('Creates a new user.')
            ->setHelp('This command allows you to create a user...');

        $this->email = new Email();
        $this->email->checkHost = true;
        $this->email->mode = Email::VALIDATION_MODE_HTML5;
        $this->validator = Validation::createValidator();

        file_put_contents($this->fileNameValid, '');
        file_put_contents($this->fileNameInValid, '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('Walidacja: data.csv');
        $this->validate('data.csv');
    }

    function validate($file): void
    {
        if (
            ($handle = fopen($file, "r")) !== FALSE && ($headers = fgetcsv($handle, 1000)) !== FALSE
        ) {

            while (($data = fgetcsv($handle, 1000)) !== FALSE) {
                $email = $data[0];
                $ve = $this->validateEmail($email);
                if (!$ve) {
                    $this->inValid++;
                    $this->saveInvalid($email);
                    continue;
                }
                $vs = $this->validateSMTP(substr($email, strrpos($email, '@') + 1));
                if (!$vs) {
                    $this->inValid++;
                    $this->saveInvalid($email);
                    continue;
                }
                $this->valid++;
            }
            fclose($handle);
        }
        $txt = 'Poprawne: ' . $this->valid . "\n";
        $txt .= 'Niepoprawne: ' . $this->inValid . "\n";
        file_put_contents($this->fileNameSumary, $txt);
    }

    private function saveInvalid(string $email): void
    {
        file_put_contents($this->fileNameInValid, $email . "\n", FILE_APPEND);
    }

    private function validateEmail($email)
    {
        $violations = $this->validator->validate($email, array(
            $this->email
        ));
        if ($violations->count()) {
            return false;
        }
        return true;
    }

    private function validateSMTP(string $mx): bool
    {
        $connect = @fsockopen($mx, 25, $errno, $errstr, 1);

        if ($connect) {
            $stat220 = preg_match('/^220/i', $out = fgets($connect, 1024));
            fclose($connect);
            if ($stat220) {
                return true;
            }
        }
        return false;
    }
}
