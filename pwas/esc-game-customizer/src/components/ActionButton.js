import React, { PureComponent, Fragment } from "react";
import { Box, Button, Dropdown } from "mineral-ui";
import { withTheme } from "emotion-theming";
import { themed } from 'mineral-ui/themes';

import { ReactComponent as ChevronRightIcon } from "../icons/ic_chevron_right.svg";

const ThemedChevronRightIcon = withTheme(({ theme }) => (
  <ChevronRightIcon fill="#fff" />
));

const LeftButton = themed(Button)({
  Button_borderRadius: "3px 0 0 3px"
});

const RightButton = themed(Button)({
  Button_borderRadius: "0 3px 3px 0"
});

const TriggerButton = themed(RightButton)({
  Button_backgroundColor_primary: "rgb(44, 106, 141)"
});

export default class ActionButton extends PureComponent {
  render() {
    return (
      <Box>
        <LeftButton {...this.props} />
        <Dropdown data={this.props.data} placement="bottom-end">
          <TriggerButton iconStart={<ThemedChevronRightIcon />} primary />
        </Dropdown>
      </Box>
    );
  }
}
