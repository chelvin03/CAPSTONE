<section class="gym-use-agreement" aria-labelledby="gym-use-agreement-heading">
    <h3 id="gym-use-agreement-heading">MCST Gymnasium Use Agreement</h3>
    <p>By submitting this reservation request, I understand and agree to follow the rules for the proper use of the MCST Gymnasium:</p>
    <ol class="gym-use-rules">
        <li><strong>No Food Inside the Gymnasium</strong><ul>
            <li>Food and meals are not allowed inside the gymnasium unless specifically permitted by the Gym Administrator.</li>
            <li>The requestor is responsible for informing all participants and guests about this rule.</li>
        </ul></li>
        <li><strong>Maintain Cleanliness</strong><ul>
            <li>The requestor and participants must keep the gymnasium clean during and after the event.</li>
            <li>Trash, decorations, bottles, papers, and other materials used during the event must be properly collected and disposed of.</li>
            <li>The area must be left clean and orderly after use.</li>
        </ul></li>
        <li><strong>Responsibility for Damages</strong><ul>
            <li>The requestor must take proper care of MCST property, including chairs, tables, equipment, facilities, and other items inside the gymnasium.</li>
            <li>If any chair, equipment, facility, or other MCST property is damaged due to the event or its participants, the incident must be reported to the Gym Administrator.</li>
            <li>The requestor may be held responsible for damages caused during their reserved event, subject to MCST policies and assessment by the authorized personnel.</li>
        </ul></li>
        <li><strong>Proper Use of the Gymnasium</strong><ul>
            <li>The gymnasium must only be used for the approved purpose, date, and time stated in the reservation.</li>
            <li>The requestor must follow instructions given by the Gym Administrator and authorized MCST personnel.</li>
        </ul></li>
    </ol>
    <label for="agreement_accepted" class="gym-use-consent">
        <input id="agreement_accepted" name="agreement_accepted" type="checkbox" x-model="policyAccepted" value="1" required @checked(old('agreement_accepted')) aria-describedby="agreement_accepted-error" aria-invalid="{{ $errors->has('agreement_accepted') ? 'true' : 'false' }}"
            @invalid="step = 3; message = 'Please read and accept the MCST Gymnasium Use Agreement before submitting your reservation request.'; $event.target.setCustomValidity(message); $event.target.setAttribute('aria-invalid', 'true')"
            @change="$event.target.setCustomValidity(''); $event.target.setAttribute('aria-invalid', 'false'); message = ''">
        <span>I have read, understood, and agree to follow the MCST Gymnasium Use Agreement and accept responsibility for the proper use of the facility during my reservation. <span class="text-red-700">*</span></span>
    </label>
    @error('agreement_accepted')<p id="agreement_accepted-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</section>
<style>
.gym-use-agreement { font-family: Poppins, sans-serif; padding: clamp(16px, 3vw, 24px); border: 1px solid #bfdbfe; border-top: 4px solid #2563EB; border-radius: 12px; background: #F8FAFC; font-size: 14px; line-height: 1.8; }
.gym-use-agreement h3 { color: #1E3A8A; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
.gym-use-rules { list-style: decimal; padding-left: 24px; margin: 18px 0; }
.gym-use-rules > li { margin-bottom: 16px; }
.gym-use-rules strong { color: #1E3A8A; }
.gym-use-rules ul { list-style: disc; padding-left: 20px; margin-top: 6px; }
.gym-use-consent { display: flex; align-items: flex-start; gap: 12px; border-top: 1px solid #bfdbfe; padding-top: 18px; font-weight: 600; }
.gym-use-consent input { flex-shrink: 0; width: 20px; height: 20px; margin-top: 3px; accent-color: #2563EB; }
</style>