<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Node;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SearchAndRenderBlockNode extends \Twig_Node_Expression_Function
{
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('$this->env->getRuntime(\'Symfony\Bridge\Twig\Form\TwigRenderer\')->searchAndRenderBlock(');

        preg_match('/_([^_]+)$/', $this->getAttribute('name'), $matches);

        $label = null;
        $arguments = iterator_to_array($this->getNode('arguments'));
        $blockNameSuffix = $matches[1];

        if (isset($arguments[0])) {
            $compiler->subcompile($arguments[0]);
            $compiler->raw(', \''.$blockNameSuffix.'\'');

            if (isset($arguments[1])) {
                if ('label' === $blockNameSuffix) {
                    // The "label" function expects the label in the second and
                    // the variables in the third argument
                    $label = $arguments[1];
                    $variables = isset($arguments[2]) ? $arguments[2] : null;
                    $lineno = $label->getLine();

                    if ($label instanceof \Twig_Node_Expression_Constant) {
                        // If the label argument is given as a constant, we can either
                        // strip it away if it is empty, or integrate it into the array
                        // of variables at compile time.
                        $labelIsExpression = false;

                        // Only insert the label into the array if it is not empty
                        if (!twig_test_empty($label->getAttribute('value'))) {
                            $originalVariables = $variables;
                            $variables = new \Twig_Node_Expression_Array(array(), $lineno);
                            $labelKey = new \Twig_Node_Expression_Constant('label', $lineno);

                            if (null !== $originalVariables) {
                                foreach ($originalVariables->getKeyValuePairs() as $pair) {
                                    // Don't copy the original label attribute over if it exists
                                    if ((string) $labelKey !== (string) $pair['key']) {
                                        $variables->addElement($pair['value'], $pair['key']);
                                    }
                                }
                            }

                            // Insert the label argument into the array
                            $variables->addElement($label, $labelKey);
                        }
                    } else {
                        // The label argument is not a constant, but some kind of
                        // expression. This expression needs to be evaluated at runtime.
                        // Depending on the result (whether it is null or not), the
                        // label in the arguments should take precedence over the label
                        // in the attributes or not.
                        $labelIsExpression = true;
                    }
                } else {
                    // All other functions than "label" expect the variables
                    // in the second argument
                    $label = null;
                    $variables = $arguments[1];
                    $labelIsExpression = false;
                }

                if (null !== $variables || $labelIsExpression) {
                    $compiler->raw(', ');

                    if (null !== $variables) {
                        $compiler->subcompile($variables);
                    }

                    if ($labelIsExpression) {
                        if (null !== $variables) {
                            $compiler->raw(' + ');
                        }

                        // Check at runtime whether the label is empty.
                        // If not, add it to the array at runtime.
                        $compiler->raw('(twig_test_empty($_label_ = ');
                        $compiler->subcompile($label);
                        $compiler->raw(') ? array() : array("label" => $_label_))');
                    }
                }
            }
        }

        $compiler->raw(')');
    }
}
