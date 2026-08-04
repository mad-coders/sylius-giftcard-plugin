<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Controller\Admin;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Exception\InvalidGiftCardAmountException;
use Madcoders\SyliusGiftCardPlugin\Form\Type\GiftCardAdjustBalanceType;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Modifier\GiftCardBalanceModifierInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Lets an admin correct a gift card's balance - a goodwill top-up, or clawing back a card issued in
 * error.
 *
 * It goes through the same modifier redemption uses, so the correction is recorded in the ledger
 * like any other balance change. There is deliberately no way to edit the balance directly on the
 * gift card form.
 */
final readonly class AdjustGiftCardBalanceController
{
    public function __construct(
        private GiftCardRepositoryInterface $giftCardRepository,
        private GiftCardBalanceModifierInterface $giftCardBalanceModifier,
        private FormFactoryInterface $formFactory,
        private ObjectManager $giftCardManager,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        // The resource repository is typed to ResourceInterface, so narrow it here rather than
        // letting a misconfigured resource class surface as a confusing error further down.
        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            throw new NotFoundHttpException(sprintf('There is no gift card with id %d.', $id));
        }

        $form = $this->formFactory->create(GiftCardAdjustBalanceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{direction: string, amount: int} $data */
            $data = $form->getData();

            try {
                if ('credit' === $data['direction']) {
                    $this->giftCardBalanceModifier->credit($giftCard, $data['amount']);
                } else {
                    $this->giftCardBalanceModifier->debit($giftCard, $data['amount']);
                }

                $this->giftCardManager->flush();
                $this->addFlash($request, 'success', 'madcoders_sylius_gift_card.admin.balance_adjusted');

                return new RedirectResponse($this->urlGenerator->generate(
                    'madcoders_sylius_gift_card_admin_gift_card_show',
                    ['id' => $giftCard->getId()],
                ));
            } catch (InvalidGiftCardAmountException $exception) {
                // The model refuses adjustments that would break its invariants (overdrawing the
                // card, or crediting it above its face value). Show the admin why rather than
                // letting a 500 out.
                $form->get('amount')->addError(new FormError($exception->getMessage()));
            }
        }

        return new Response($this->twig->render(
            '@MadcodersSyliusGiftCardPlugin/admin/gift_card/adjust_balance.html.twig',
            ['gift_card' => $giftCard, 'form' => $form->createView()],
        ));
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();

        if ($session instanceof Session) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
