<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Fixer\Import;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

/**
 * Fixer for rules defined in PSR2 ¶3.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 */
final class SingleImportPerStatementFixer extends AbstractFixer
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_USE);
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);
        $uses = array_reverse($tokensAnalyzer->getImportUseIndexes());

        foreach ($uses as $index) {
            $endIndex = $tokens->getNextTokenOfKind($index, array(';', array(T_CLOSE_TAG)));
            $previous = $tokens->getPrevMeaningfulToken($endIndex);

            if ($tokens[$previous]->isGivenKind(CT_GROUP_IMPORT_BRACE_CLOSE)) {
                $start = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_GROUP_IMPORT_BRACE, $previous, false);
                $declarationContent = $tokens->generatePartialCode($start + 1, $previous - 1);
                $prefix = '';
                for ($i = $index + 1; $i < $start; ++$i) {
                    $prefix .= $tokens[$i]->getContent();
                }

                $prefix = ' '.ltrim($prefix);
            } else {
                $declarationContent = $tokens->generatePartialCode($index + 1, $endIndex - 1);
                $prefix = ' ';
            }

            $declarationParts = explode(',', $declarationContent);
            if (1 === count($declarationParts)) {
                continue;
            }

            $declarationContent = array();

            foreach ($declarationParts as $declarationPart) {
                $declarationContent[] = 'use'.$prefix.trim($declarationPart).';';
            }

            $declarationContent = implode("\n".$this->detectIndent($tokens, $index), $declarationContent);

            for ($i = $index; $i < $endIndex; ++$i) {
                $tokens[$i]->clear();
            }

            if ($tokens[$endIndex]->equals(';')) {
                $tokens[$endIndex]->clear();
            }

            $declarationTokens = Tokens::fromCode('<?php '.$declarationContent);
            $declarationTokens[0]->clear();
            $declarationTokens->clearEmptyTokens();

            $tokens->insertAt($index, $declarationTokens);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return 'There MUST be one use keyword per declaration.';
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     *
     * @return string
     */
    private function detectIndent(Tokens $tokens, $index)
    {
        $prevIndex = $index - 1;
        $prevToken = $tokens[$prevIndex];

        // if can not detect indent:
        if (!$prevToken->isWhitespace()) {
            return '';
        }

        $explodedContent = explode("\n", $prevToken->getContent());

        return end($explodedContent);
    }
}
