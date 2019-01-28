<?php

declare(strict_types=1);

/*
 * This file is part of gpupo/questions-for-developers
 * Created by Gilmar Pupo <contact@gpupo.com>
 * For the information of copyright and license you should read the file
 * LICENSE which is distributed with this source code.
 * Para a informação dos direitos autorais e de licença você deve ler o arquivo
 * LICENSE que é distribuído com este código-fonte.
 * Para obtener la información de los derechos de autor y la licencia debe leer
 * el archivo LICENSE que se distribuye con el código fuente.
 * For more information, see <https://opensource.gpupo.com/>.
 *
 */

namespace Gpupo\QuestionsForDevelopers\Console\Command\Questions;

use Certificationy\Collections\Questions;
use Certificationy\Loaders\YamlLoader as Loader;
use Certificationy\Set;
use Gpupo\QuestionsForDevelopers\Console\Command\AbstractCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

final class StartCommand extends AbstractCommand
{
    const WORDWRAP_NUMBER = 80;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('questions:start')
            ->setDescription('Starts a new question set')
            ->addArgument('categories', InputArgument::IS_ARRAY, 'Which categories do you want (separate multiple with a space)', [])
            ->addOption('number', null, InputOption::VALUE_OPTIONAL, 'How many questions do you want?', 20)
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List categories')
            ->addOption('training', null, InputOption::VALUE_NONE, 'Training mode: the solution is displayed after each question')
            ->addOption('hide-multiple-choice', null, InputOption::VALUE_NONE, 'Should we hide the information that the question is multiple choice?')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Use custom config', null);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $yamlLoader = new Loader($data['paths']);
        if ($input->getOption('list')) {
            $output->writeln($yamlLoader->categories());

            return;
        }

        $categories = $input->getArgument('categories');
        $number = $input->getOption('number');

        $set = Set::create($yamlLoader->load($number, $categories));

        if ($set->getQuestions()) {
            $output->writeln(
                    sprintf('Starting a new set of <info>%s</info> questions (available questions: <info>%s</info>)', \count($set->getQuestions()), \count($yamlLoader->all()))
                );

            $this->askQuestions($set, $input, $output);
            $this->displayResults($set, $output);
        } else {
            $output->writeln('<error>✗</error> No questions can be found.');
        }
    }

    protected function askQuestions(Set $set, InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getHelper('question');
        $hideMultipleChoice = $input->getOption('hide-multiple-choice');
        $questionCount = 1;

        foreach ($set->getQuestions()->all() as $i => $question) {
            $choiceQuestion = new ChoiceQuestion(
                    sprintf(
                        'Question <comment>#%d</comment> [<info>%s</info>] %s %s'."\n",
                        $questionCount++,
                        $question->getCategory(),
                        $question->getQuestion(),
                        (true === $hideMultipleChoice ? '' : "\n".'This question <comment>'.(true === $question->isMultipleChoice() ? 'IS' : 'IS NOT').'</comment> multiple choice.')
                    ),
                    $question->getAnswersLabels()
                );

            $multiSelect = true === $hideMultipleChoice ? true : $question->isMultipleChoice();
            $numericOnly = 1 === array_product(array_map('is_numeric', $question->getAnswersLabels()));
            $choiceQuestion->setMultiselect($multiSelect);
            $choiceQuestion->setErrorMessage('Answer %s is invalid.');
            $choiceQuestion->setAutocompleterValues($numericOnly ? null : $question->getAnswersLabels());

            $answer = $questionHelper->ask($input, $output, $choiceQuestion);

            $answers = true === $multiSelect ? $answer : [$answer];
            $answer = true === $multiSelect ? implode(', ', $answer) : $answer;

            $set->setUserAnswers($i, $answers);

            if ($input->getOption('training')) {
                $uniqueSet = Set::create(new Questions([$i => $question]));

                $uniqueSet->setUserAnswers($i, $answers);

                $this->displayResults($uniqueSet, $output);
            }

            $output->writeln(sprintf('<comment>✎ Your answer</comment>: %s', $answer));
            $output->writeln('');
        }
    }

    protected function displayResults(Set $set, OutputInterface $output)
    {
        $results = [];

        $questionCount = 0;

        foreach ($set->getQuestions()->all() as $key => $question) {
            $isCorrect = $set->isCorrect($key);
            ++$questionCount;
            $label = wordwrap($question->getQuestion(), self::WORDWRAP_NUMBER, "\n");
            $help = $question->getHelp();

            $results[] = [
                    sprintf('<comment>#%d</comment> %s', $questionCount, $label),
                    wordwrap(implode(', ', $question->getCorrectAnswersValues()), self::WORDWRAP_NUMBER, "\n"),
                    $isCorrect ? '<info>✔</info>' : '<error>✗</error>',
                    (null !== $help) ? wordwrap($help, self::WORDWRAP_NUMBER, "\n") : '',
                ];
        }

        if ($results) {
            $tableHelper = new Table($output);
            $tableHelper
                ->setHeaders(['Question', 'Correct answer', 'Result', 'Help'])
                ->setRows($results)
                ;

            $tableHelper->render();

            $output->writeln(
                    sprintf('<comment>Results</comment>: <error>errors: %s</error> - <info>correct: %s</info>', $set->getWrongAnswers()->count(), $set->getCorrectAnswers()->count())
                );
        }
    }
}
